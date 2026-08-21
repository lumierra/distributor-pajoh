<?php

namespace App\Services\Inventory;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\StockLedger;
use App\Models\StockOpening;
use App\Models\User;
use App\Services\Numbering\NumberingService;
use App\Services\Purchasing\BatchResolver;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Orchestrator Stok Awal (opening balance). User memilih SATUAN per item (mis.
 * 10 KRT); qty disimpan dalam UoM tsb + `qty_base` hasil konversi (× qty_to_base).
 * Saat posting, tiap item membuat/menemukan batch (BatchResolver) lalu menulis
 * stock_ledger opening_in sebesar qty_base (atomic). Berbeda dari Adjustment,
 * produk boleh yang belum punya stok/batch sama sekali.
 */
class StockOpeningService
{
    public const DEFAULT_BATCH_CODE = 'OPENING';

    public function __construct(
        private readonly NumberingService $numbering,
        private readonly StockLedgerWriter $ledger,
        private readonly BatchResolver $batchResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $headerData
     * @param  array<int, array<string, mixed>>  $itemsData
     */
    public function createDraft(array $headerData, array $itemsData, User $by): StockOpening
    {
        return DB::transaction(function () use ($headerData, $itemsData, $by): StockOpening {
            $date = $this->parseDate($headerData['opening_date']);

            $opening = new StockOpening([
                'opening_date' => $date,
                'notes' => $headerData['notes'] ?? null,
                'status' => StockOpening::STATUS_DRAFT,
                'fiscal_year' => (int) $date->format('Y'),
                'created_by' => $by->id,
            ]);
            $opening->opening_number = $this->numbering->next('opening');
            $opening->save();

            $this->syncItems($opening, $itemsData);

            return $opening->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $headerData
     * @param  array<int, array<string, mixed>>  $itemsData
     */
    public function update(StockOpening $opening, array $headerData, array $itemsData, User $by): StockOpening
    {
        if (! $opening->canBeEdited()) {
            throw ValidationException::withMessages(['status' => 'Stok awal tidak bisa diedit pada status saat ini.']);
        }

        return DB::transaction(function () use ($opening, $headerData, $itemsData, $by): StockOpening {
            $date = $this->parseDate($headerData['opening_date']);

            $opening->update([
                'opening_date' => $date,
                'notes' => $headerData['notes'] ?? null,
                'fiscal_year' => (int) $date->format('Y'),
                'updated_by' => $by->id,
            ]);

            $this->syncItems($opening, $itemsData);

            return $opening->refresh();
        });
    }

    /**
     * Posting: buat batch & tulis ledger opening_in per item. Atomic.
     */
    public function post(StockOpening $opening, User $by): StockOpening
    {
        if (! $opening->canBePosted()) {
            throw ValidationException::withMessages(['status' => 'Stok awal tidak bisa di-posting (status atau item kosong).']);
        }

        $opening->load(['items.product:id,base_unit_id', 'items.productUnit:id,qty_to_base']);

        return DB::transaction(function () use ($opening, $by): StockOpening {
            foreach ($opening->items as $item) {
                $baseUnitId = $item->product?->base_unit_id;
                if ($baseUnitId === null) {
                    throw ValidationException::withMessages([
                        'items' => "Produk {$item->product_sku_snapshot} tidak punya base unit.",
                    ]);
                }

                // Buat / temukan batch (produk baru belum punya batch).
                $batch = $this->batchResolver->resolveOrCreate([
                    'product_id' => $item->product_id,
                    'supplier_id' => $item->supplier_id,
                    'batch_code' => $item->batch_code,
                    'production_date' => null,
                    'expired_date' => $item->expired_date?->toDateString(),
                ]);

                // Qty yang masuk stok = qty_base (sudah dikonversi dari UoM dipilih).
                $qtyBase = (int) $item->qty_base;
                $qtyBonusBase = (int) $item->qty_bonus_base;
                $factor = (int) ($item->productUnit?->qty_to_base ?? 1);
                // Harga modal per BASE unit (user input per UoM dipilih).
                $costBase = $factor > 0 ? round((float) $item->cost_price / $factor, 4) : (float) $item->cost_price;

                $item->update(['batch_id' => $batch->id]);

                $batch->initial_qty_base += $qtyBase + $qtyBonusBase;
                $batch->last_received_at = now();
                $batch->save();

                // Stok reguler (opening_in).
                if ($qtyBase > 0) {
                    $this->ledger->writeIn([
                        'product_id' => $item->product_id,
                        'batch_id' => $batch->id,
                        'product_unit_id' => $baseUnitId,
                        'type' => StockLedger::TYPE_OPENING_IN,
                        'qty_in' => $qtyBase,
                        'cost_price' => $costBase,
                        'ref_type' => 'stock_opening',
                        'ref_id' => $opening->id,
                        'notes' => $item->notes,
                    ], $by);
                }

                // Stok bonus (bonus_in → bonus_pool, harga 0).
                if ($qtyBonusBase > 0) {
                    $this->ledger->writeIn([
                        'product_id' => $item->product_id,
                        'batch_id' => $batch->id,
                        'product_unit_id' => $baseUnitId,
                        'type' => StockLedger::TYPE_BONUS_IN,
                        'is_bonus_pool' => true,
                        'qty_in' => $qtyBonusBase,
                        'cost_price' => 0,
                        'ref_type' => 'stock_opening',
                        'ref_id' => $opening->id,
                        'notes' => $item->notes,
                    ], $by);
                }
            }

            $opening->update([
                'status' => StockOpening::STATUS_POSTED,
                'posted_by' => $by->id,
                'posted_at' => now(),
            ]);

            return $opening->refresh();
        });
    }

    public function cancel(StockOpening $opening, string $reason, User $by): StockOpening
    {
        if (! $opening->canBeCancelled()) {
            throw ValidationException::withMessages(['status' => 'Hanya stok awal draft yang bisa dibatalkan.']);
        }

        $opening->update([
            'status' => StockOpening::STATUS_CANCELLED,
            'cancelled_by' => $by->id,
            'cancelled_at' => now(),
            'cancel_reason' => $reason,
        ]);

        return $opening->refresh();
    }

    /**
     * Replace item. Snapshot identitas produk; batch_code default "OPENING"
     * kalau kosong.
     *
     * @param  array<int, array<string, mixed>>  $itemsData
     */
    private function syncItems(StockOpening $opening, array $itemsData): void
    {
        $opening->items()->delete();

        foreach ($itemsData as $idx => $row) {
            /** @var Product $product */
            $product = Product::query()->findOrFail($row['product_id']);

            // Satuan wajib & harus milik produk ini. Fallback ke base unit produk.
            $unitId = (int) ($row['product_unit_id'] ?? 0) ?: (int) $product->base_unit_id;
            /** @var ProductUnit $unit */
            $unit = ProductUnit::query()
                ->where('product_id', $product->id)
                ->find($unitId);
            if ($unit === null) {
                throw ValidationException::withMessages([
                    "items.{$idx}.product_unit_id" => 'Satuan tidak sesuai dengan produk.',
                ]);
            }

            $qty = (int) ($row['qty'] ?? 0);
            $qtyBonus = (int) ($row['qty_bonus'] ?? 0);
            if ($qty <= 0 && $qtyBonus <= 0) {
                throw ValidationException::withMessages([
                    "items.{$idx}.qty" => 'Qty reguler atau bonus harus diisi (> 0).',
                ]);
            }
            if ($qtyBonus < 0) {
                $qtyBonus = 0;
            }

            $batchCode = trim((string) ($row['batch_code'] ?? ''));
            if ($batchCode === '') {
                $batchCode = self::DEFAULT_BATCH_CODE;
            }

            $factor = (int) $unit->qty_to_base;

            $opening->items()->create([
                'product_id' => $product->id,
                'product_unit_id' => $unit->id,
                'product_unit_name_snapshot' => $unit->name,
                'supplier_id' => $row['supplier_id'] ?? $product->supplier_id,
                'batch_code' => $batchCode,
                'expired_date' => $row['expired_date'] ?? null,
                'product_name_snapshot' => $product->name,
                'product_sku_snapshot' => $product->sku,
                'qty' => $qty,
                'qty_base' => $qty * $factor,
                'qty_bonus' => $qtyBonus,
                'qty_bonus_base' => $qtyBonus * $factor,
                'cost_price' => (float) ($row['cost_price'] ?? 0),
                'notes' => $row['notes'] ?? null,
                'sort_order' => $idx,
            ]);
        }
    }

    private function parseDate(mixed $value): CarbonInterface
    {
        return $value instanceof CarbonInterface ? $value : Carbon::parse($value);
    }
}
