<?php

namespace App\Services\Inventory;

use App\Exceptions\InsufficientStockException;
use App\Models\DoItem;
use App\Models\ProductUnit;
use App\Models\SalesOrder;
use App\Models\SoReservation;
use App\Models\StockLedger;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Konsumsi stok untuk Sales Order. Model baru: stok fisik LANGSUNG DIPOTONG
 * saat SO di-approve (bukan saat DO delivered). Pakai FifoPicker untuk pilih
 * batch, tulis ledger `sale_out` per batch (qty_on_hand turun), lalu catat
 * `so_reservations` dengan status CONSUMED sebagai catatan batch mana yang
 * dipakai — dipakai DeliveryOrderService untuk menyusun DO.
 *
 * `stock_balances.qty_reserved` TIDAK dipakai lagi (tidak ada tahap "hold");
 * kolom `so_reservations.qty_reserved` di sini bermakna "qty base yang diambil
 * per batch" (catatan), bukan hold.
 *
 * Untuk bonus items (is_bonus=true) — coba ambil dari `qty_bonus_pool` dulu
 * (preferBonus=true). Kalau tidak cukup, picker fallback ke regular stock.
 */
class ReservationService
{
    public function __construct(
        private readonly FifoPicker $picker,
        private readonly StockLedgerWriter $ledger,
    ) {}

    /**
     * Potong stok untuk semua item SO saat approve. Atomic — gagal salah satu,
     * rollback semua. Menulis `sale_out` per batch (ref SO) & mencatat batch di
     * `so_reservations` (status CONSUMED) untuk penyusunan DO nanti.
     *
     * @return int jumlah row catatan batch yang dibuat
     */
    public function consumeForSo(SalesOrder $so, ?User $by = null): int
    {
        $created = 0;

        DB::transaction(function () use ($so, $by, &$created): void {
            $so->loadMissing(['items.productUnit', 'items.product']);

            foreach ($so->items as $item) {
                /** @var ProductUnit $unit */
                $unit = $item->productUnit;
                $qtyBase = (int) $item->qty * (int) $unit->qty_to_base;

                $picks = $this->picker->pick(
                    productId: $item->product_id,
                    qtyBase: $qtyBase,
                    preferBonus: (bool) $item->is_bonus,
                );

                foreach ($picks as $pick) {
                    // Potong stok fisik sekarang (sale_out).
                    $this->ledger->writeOut([
                        'product_id' => $item->product_id,
                        'batch_id' => $pick['batch_id'],
                        'product_unit_id' => $unit->id,
                        'type' => StockLedger::TYPE_SALE_OUT,
                        'is_bonus_pool' => (bool) $pick['is_bonus_pool'],
                        'qty_out' => (int) $pick['qty'],
                        'cost_price' => (float) $pick['cost_price'],
                        'ref_type' => 'SO',
                        'ref_id' => $so->id,
                    ], $by);

                    // Catat batch yang dipakai (bukan hold) untuk penyusunan DO.
                    SoReservation::create([
                        'sales_order_id' => $so->id,
                        'so_item_id' => $item->id,
                        'product_id' => $item->product_id,
                        'batch_id' => $pick['batch_id'],
                        'qty_reserved' => $pick['qty'],
                        'status' => SoReservation::STATUS_CONSUMED,
                        'consumed_at' => now(),
                    ]);

                    $created++;
                }
            }
        });

        return $created;
    }

    /**
     * Kembalikan stok SO saat SO di-cancel setelah approve. Untuk tiap catatan
     * batch yang BELUM dikirim lewat DO (belum ada consumed_by_do_id), tulis
     * ledger `return_in` ke batch semula (qty_on_hand naik) dan set catatan →
     * RELEASED. Catatan yang sudah dikirim lewat DO tidak dikembalikan (barang
     * sudah keluar fisik; itu urusan retur customer).
     *
     * @return int jumlah row yang dikembalikan
     */
    public function returnForSo(SalesOrder $so, string $reason, ?User $by = null): int
    {
        $returned = 0;

        DB::transaction(function () use ($so, $reason, $by, &$returned): void {
            $so->loadMissing(['items.productUnit']);
            $unitByItem = $so->items->keyBy('id');

            $records = SoReservation::query()
                ->where('sales_order_id', $so->id)
                ->where('status', SoReservation::STATUS_CONSUMED)
                ->whereNull('consumed_by_do_id')
                ->lockForUpdate()
                ->get();

            foreach ($records as $res) {
                $soItem = $unitByItem->get($res->so_item_id);
                $unitId = $soItem?->productUnit?->id;
                $isBonus = (bool) ($soItem?->is_bonus);
                $cost = $this->batchCostFor($res->product_id, $res->batch_id);

                $this->ledger->writeIn([
                    'product_id' => $res->product_id,
                    'batch_id' => $res->batch_id,
                    'product_unit_id' => $unitId,
                    'type' => StockLedger::TYPE_RETURN_IN,
                    'is_bonus_pool' => $isBonus,
                    'qty_in' => (int) $res->qty_reserved,
                    'cost_price' => $cost,
                    'ref_type' => 'SO',
                    'ref_id' => $so->id,
                    'notes' => $reason,
                ], $by);

                $res->update([
                    'status' => SoReservation::STATUS_RELEASED,
                    'released_at' => now(),
                    'released_reason' => $reason,
                ]);

                $returned++;
            }
        });

        return $returned;
    }

    /**
     * Cost per unit batch dari ledger IN terakhir (untuk nilai return_in).
     */
    private function batchCostFor(int $productId, int $batchId): float
    {
        $cost = StockLedger::query()
            ->where('product_id', $productId)
            ->where('batch_id', $batchId)
            ->where('qty_in', '>', 0)
            ->orderByDesc('id')
            ->value('cost_price');

        return (float) ($cost ?? 0);
    }

    /**
     * Cek apakah stok tersedia untuk semua items SO tanpa benar-benar reserve.
     * Throw InsufficientStockException kalau ada item yang kurang.
     */
    public function assertStockAvailable(SalesOrder $so): void
    {
        $so->loadMissing(['items.productUnit']);

        foreach ($so->items as $item) {
            $unit = $item->productUnit;
            $qtyBase = (int) $item->qty * (int) $unit->qty_to_base;

            // pick() throw InsufficientStockException kalau gagal — kita
            // discard hasilnya, ini cuma cek.
            $this->picker->pick(
                productId: $item->product_id,
                qtyBase: $qtyBase,
                preferBonus: (bool) $item->is_bonus,
            );
        }
    }

    /**
     * Tandai catatan batch (reservation) sebagai sudah dikirim lewat DO ini.
     * Model baru: stok fisik SUDAH dipotong saat SO approve, jadi di sini TIDAK
     * ada `sale_out` lagi dan TIDAK menyentuh qty_reserved. Barang yang di-retur
     * customer dikembalikan terpisah lewat CustomerReturn (return_in).
     *
     * Atomic. Dipanggil dari DeliveryOrderService::markDelivered().
     */
    public function consumeForDoItem(DoItem $item, User $by): void
    {
        DB::transaction(function () use ($item): void {
            $item->loadMissing(['reservation']);

            if ($item->reservation !== null) {
                $item->reservation->update([
                    'status' => SoReservation::STATUS_CONSUMED,
                    'consumed_at' => $item->reservation->consumed_at ?? now(),
                    'consumed_by_do_id' => $item->delivery_order_id,
                ]);
            }
        });
    }
}
