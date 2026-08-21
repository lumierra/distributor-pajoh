<?php

namespace App\Services\Inventory;

use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductUnit;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\StockBalance;
use App\Models\StockLedger;
use App\Models\User;
use App\Services\Numbering\NumberingService;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Orchestrator penyesuaian stok manual. Qty item dalam BASE UNIT (sama dengan
 * satuan stok). Saat posting, tiap item menulis stock_ledger via
 * StockLedgerWriter (atomic + cek stok cukup untuk arah OUT).
 */
class StockAdjustmentService
{
    public function __construct(
        private readonly NumberingService $numbering,
        private readonly StockLedgerWriter $ledger,
    ) {}

    /**
     * @param  array<string, mixed>  $headerData
     * @param  array<int, array<string, mixed>>  $itemsData
     */
    public function createDraft(array $headerData, array $itemsData, User $by): StockAdjustment
    {
        return DB::transaction(function () use ($headerData, $itemsData, $by): StockAdjustment {
            $date = $headerData['adjustment_date'] instanceof CarbonInterface
                ? $headerData['adjustment_date']
                : Carbon::parse($headerData['adjustment_date']);

            $adjustment = new StockAdjustment([
                'adjustment_date' => $date,
                'reason_category' => $headerData['reason_category'],
                'notes' => $headerData['notes'] ?? null,
                'status' => StockAdjustment::STATUS_DRAFT,
                'fiscal_year' => (int) $date->format('Y'),
                'created_by' => $by->id,
            ]);
            $adjustment->adjustment_number = $this->numbering->next('adjustment');
            $adjustment->save();

            $this->syncItems($adjustment, $itemsData);

            return $adjustment->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $headerData
     * @param  array<int, array<string, mixed>>  $itemsData
     */
    public function update(StockAdjustment $adjustment, array $headerData, array $itemsData, User $by): StockAdjustment
    {
        if (! $adjustment->canBeEdited()) {
            throw ValidationException::withMessages(['status' => 'Adjustment tidak bisa diedit pada status saat ini.']);
        }

        return DB::transaction(function () use ($adjustment, $headerData, $itemsData, $by): StockAdjustment {
            $date = $headerData['adjustment_date'] instanceof CarbonInterface
                ? $headerData['adjustment_date']
                : Carbon::parse($headerData['adjustment_date']);

            $adjustment->update([
                'adjustment_date' => $date,
                'reason_category' => $headerData['reason_category'],
                'notes' => $headerData['notes'] ?? null,
                'fiscal_year' => (int) $date->format('Y'),
                'updated_by' => $by->id,
            ]);

            $this->syncItems($adjustment, $itemsData);

            return $adjustment->refresh();
        });
    }

    /**
     * Posting: tulis ledger adjustment_in/out per item, update stok. Atomic.
     */
    public function post(StockAdjustment $adjustment, User $by): StockAdjustment
    {
        if (! $adjustment->canBePosted()) {
            throw ValidationException::withMessages(['status' => 'Adjustment tidak bisa di-posting (status atau item kosong).']);
        }

        $adjustment->load('items.product:id,base_unit_id');

        return DB::transaction(function () use ($adjustment, $by): StockAdjustment {
            foreach ($adjustment->items as $item) {
                // Unit yang di-set item (fallback base unit utk data lama).
                $unitId = $item->product_unit_id ?? $item->product?->base_unit_id;
                if ($unitId === null) {
                    throw ValidationException::withMessages([
                        'items' => "Produk {$item->product_sku_snapshot} tidak punya base unit.",
                    ]);
                }

                // Qty base = hasil konversi. Data lama (qty_base=0) fallback ke qty.
                $qtyBase = (int) $item->qty_base > 0 ? (int) $item->qty_base : (int) $item->qty;

                $payload = [
                    'product_id' => $item->product_id,
                    'batch_id' => $item->batch_id,
                    'product_unit_id' => $unitId,
                    'ref_type' => 'stock_adjustment',
                    'ref_id' => $adjustment->id,
                    'cost_price' => (float) $item->cost_price,
                    'notes' => $item->notes,
                ];

                if ($item->direction === StockAdjustmentItem::DIRECTION_IN) {
                    $this->ledger->writeIn([
                        ...$payload,
                        'type' => StockLedger::TYPE_ADJUSTMENT_IN,
                        'qty_in' => $qtyBase,
                    ], $by);
                } else {
                    $this->ledger->writeOut([
                        ...$payload,
                        'type' => StockLedger::TYPE_ADJUSTMENT_OUT,
                        'qty_out' => $qtyBase,
                    ], $by);
                }
            }

            $adjustment->update([
                'status' => StockAdjustment::STATUS_POSTED,
                'posted_by' => $by->id,
                'posted_at' => now(),
            ]);

            return $adjustment->refresh();
        });
    }

    public function cancel(StockAdjustment $adjustment, string $reason, User $by): StockAdjustment
    {
        if (! $adjustment->canBeCancelled()) {
            throw ValidationException::withMessages(['status' => 'Hanya adjustment draft yang bisa dibatalkan.']);
        }

        $adjustment->update([
            'status' => StockAdjustment::STATUS_CANCELLED,
            'cancelled_by' => $by->id,
            'cancelled_at' => now(),
            'cancel_reason' => $reason,
        ]);

        return $adjustment->refresh();
    }

    /**
     * Replace item. Validasi: batch milik produk, qty > 0, arah valid; snapshot
     * identitas & stok sistem saat ini.
     *
     * @param  array<int, array<string, mixed>>  $itemsData
     */
    private function syncItems(StockAdjustment $adjustment, array $itemsData): void
    {
        $adjustment->items()->delete();

        foreach ($itemsData as $idx => $row) {
            /** @var Product $product */
            $product = Product::query()->findOrFail($row['product_id']);
            /** @var ProductBatch $batch */
            $batch = ProductBatch::query()
                ->where('product_id', $product->id)
                ->findOrFail($row['batch_id']);

            $direction = $row['direction'] ?? StockAdjustmentItem::DIRECTION_IN;
            if (! in_array($direction, StockAdjustmentItem::DIRECTIONS, true)) {
                throw ValidationException::withMessages([
                    "items.{$idx}.direction" => 'Arah penyesuaian tidak valid.',
                ]);
            }

            $qty = (int) ($row['qty'] ?? 0);
            if ($qty <= 0) {
                throw ValidationException::withMessages([
                    "items.{$idx}.qty" => 'Qty harus lebih dari 0.',
                ]);
            }

            // Satuan yang dipilih (default base unit). Validasi milik produk ini.
            $unitId = $row['product_unit_id'] ?? $product->base_unit_id;
            /** @var ProductUnit $unit */
            $unit = ProductUnit::query()
                ->where('product_id', $product->id)
                ->find($unitId);
            if ($unit === null) {
                throw ValidationException::withMessages([
                    "items.{$idx}.product_unit_id" => 'Satuan tidak valid untuk produk ini.',
                ]);
            }
            $qtyBase = $qty * (int) $unit->qty_to_base;

            $systemQty = (int) (StockBalance::query()
                ->where('product_id', $product->id)
                ->where('batch_id', $batch->id)
                ->value('qty_on_hand') ?? 0);

            $adjustment->items()->create([
                'product_id' => $product->id,
                'product_unit_id' => $unit->id,
                'product_unit_name_snapshot' => $unit->name,
                'batch_id' => $batch->id,
                'product_name_snapshot' => $product->name,
                'product_sku_snapshot' => $product->sku,
                'batch_code_snapshot' => $batch->batch_code,
                'direction' => $direction,
                'qty' => $qty,
                'qty_base' => $qtyBase,
                'system_qty_snapshot' => $systemQty,
                'cost_price' => (float) ($row['cost_price'] ?? 0),
                'notes' => $row['notes'] ?? null,
                'sort_order' => $idx,
            ]);
        }
    }
}
