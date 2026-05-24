<?php

namespace App\Services\Inventory;

use App\Exceptions\InsufficientStockException;
use App\Models\DoItem;
use App\Models\ProductUnit;
use App\Models\SalesOrder;
use App\Models\SoReservation;
use App\Models\StockBalance;
use App\Models\StockLedger;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Reserve stok untuk Sales Order. Pakai FifoPicker untuk pilih batch,
 * lalu insert `so_reservations` rows + increment `stock_balances.qty_reserved`.
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
     * Reserve stock untuk semua items di SO. Atomic — gagal salah satu,
     * rollback semua (termasuk yang sudah ter-reserve).
     *
     * @return int jumlah row reservation yang dibuat
     */
    public function reserveForSo(SalesOrder $so): int
    {
        $created = 0;

        DB::transaction(function () use ($so, &$created): void {
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
                    SoReservation::create([
                        'sales_order_id' => $so->id,
                        'so_item_id' => $item->id,
                        'product_id' => $item->product_id,
                        'batch_id' => $pick['batch_id'],
                        'qty_reserved' => $pick['qty'],
                        'status' => SoReservation::STATUS_ACTIVE,
                    ]);

                    // Bump qty_reserved di balance (atomic)
                    StockBalance::query()
                        ->where('product_id', $item->product_id)
                        ->where('batch_id', $pick['batch_id'])
                        ->lockForUpdate()
                        ->increment('qty_reserved', $pick['qty']);

                    $created++;
                }
            }
        });

        return $created;
    }

    /**
     * Release semua reservation aktif untuk SO. Dipanggil saat SO cancelled
     * setelah approved.
     */
    public function releaseForSo(SalesOrder $so, string $reason): int
    {
        $released = 0;

        DB::transaction(function () use ($so, $reason, &$released): void {
            $activeReservations = SoReservation::query()
                ->where('sales_order_id', $so->id)
                ->where('status', SoReservation::STATUS_ACTIVE)
                ->lockForUpdate()
                ->get();

            foreach ($activeReservations as $res) {
                // Decrement qty_reserved
                StockBalance::query()
                    ->where('product_id', $res->product_id)
                    ->where('batch_id', $res->batch_id)
                    ->lockForUpdate()
                    ->decrement('qty_reserved', $res->qty_reserved);

                $res->update([
                    'status' => SoReservation::STATUS_RELEASED,
                    'released_at' => now(),
                    'released_reason' => $reason,
                ]);

                $released++;
            }
        });

        return $released;
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
     * Consume reservation untuk DoItem yang sudah delivered:
     *  1. Tulis stock_ledger sale_out → balance.qty_on_hand decrement
     *  2. Release reservation (kalau ada) → balance.qty_reserved decrement
     *
     * Atomic. Dipanggil dari DeliveryOrderService::markDelivered().
     */
    public function consumeForDoItem(DoItem $item, User $by): void
    {
        DB::transaction(function () use ($item, $by): void {
            $item->loadMissing(['productUnit', 'reservation']);

            /** @var ProductUnit $unit */
            $unit = $item->productUnit;
            $qtyDelivered = (int) $item->qty_delivered;
            $qtyReturned = (int) $item->qty_returned;
            $qtyToConsume = max(0, $qtyDelivered - $qtyReturned);

            if ($qtyToConsume <= 0 && $item->reservation !== null) {
                // Semua di-return: release reservation, tidak ada sale_out.
                $this->releaseReservation($item->reservation, 'All qty returned saat delivered.');

                return;
            }

            if ($qtyToConsume > 0) {
                $qtyBase = $qtyToConsume * (int) $unit->qty_to_base;

                $this->ledger->writeOut([
                    'product_id' => $item->product_id,
                    'batch_id' => $item->batch_id,
                    'product_unit_id' => $unit->id,
                    'type' => StockLedger::TYPE_SALE_OUT,
                    'is_bonus_pool' => (bool) $item->is_bonus,
                    'qty_out' => $qtyBase,
                    'cost_price' => (float) ($item->cost_price_base ?? 0),
                    'ref_type' => 'DO',
                    'ref_id' => $item->delivery_order_id,
                ], $by);
            }

            // Update reservation: consumed atau released parsial.
            if ($item->reservation !== null) {
                $res = $item->reservation;

                // Decrement qty_reserved sebesar planned (yang ter-hold). Bukan
                // delivered. Karena reservation memang menahan planned qty.
                $plannedBase = (int) $item->qty_planned * (int) $unit->qty_to_base;

                StockBalance::query()
                    ->where('product_id', $item->product_id)
                    ->where('batch_id', $item->batch_id)
                    ->lockForUpdate()
                    ->decrement('qty_reserved', $plannedBase);

                $res->update([
                    'status' => SoReservation::STATUS_CONSUMED,
                    'consumed_at' => now(),
                    'consumed_by_do_id' => $item->delivery_order_id,
                ]);
            }
        });
    }

    private function releaseReservation(SoReservation $res, string $reason): void
    {
        StockBalance::query()
            ->where('product_id', $res->product_id)
            ->where('batch_id', $res->batch_id)
            ->lockForUpdate()
            ->decrement('qty_reserved', (int) $res->qty_reserved);

        $res->update([
            'status' => SoReservation::STATUS_RELEASED,
            'released_at' => now(),
            'released_reason' => $reason,
        ]);
    }
}
