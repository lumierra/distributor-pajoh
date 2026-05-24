<?php

namespace App\Services\SupplierReturn;

use App\Models\CustomerReturnItem;
use App\Models\GrnItem;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductUnit;
use App\Models\StockBalance;
use App\Models\StockLedger;
use App\Models\Supplier;
use App\Models\SupplierReturn;
use App\Models\SupplierReturnItem;
use App\Models\User;
use App\Services\Inventory\StockLedgerWriter;
use App\Services\Numbering\NumberingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierReturnService
{
    public function __construct(
        private readonly NumberingService $numbering,
        private readonly StockLedgerWriter $stockWriter,
    ) {}

    /**
     * Create supplier return draft.
     *
     * @param  array{
     *   supplier_id:int, return_date:string|\DateTimeInterface,
     *   reason_code:string, reason_notes?:string|null, notes?:string|null,
     *   proof_photo_path?:string|null,
     *   items: array<int, array{
     *     source_type:string, source_id?:int|null,
     *     grn_item_id?:int|null, customer_return_item_id?:int|null,
     *     product_id:int, product_unit_id:int, qty:int, cost_price:float,
     *     batch_id?:int|null, notes?:string|null
     *   }>
     * }  $data
     */
    public function createDraft(array $data, User $by): SupplierReturn
    {
        return DB::transaction(function () use ($data, $by): SupplierReturn {
            /** @var Supplier $supplier */
            $supplier = Supplier::query()->findOrFail($data['supplier_id']);

            $sr = new SupplierReturn([
                'supplier_id' => $supplier->id,
                'supplier_snapshot' => $this->buildSupplierSnapshot($supplier),
                'return_date' => $data['return_date'],
                'reason_code' => $data['reason_code'],
                'reason_notes' => $data['reason_notes'] ?? null,
                'status' => SupplierReturn::STATUS_DRAFT,
                'fiscal_year' => (int) Carbon::parse($data['return_date'])->format('Y'),
                'notes' => $data['notes'] ?? null,
                'proof_photo_path' => $data['proof_photo_path'] ?? null,
                'created_by' => $by->id,
            ]);
            $sr->return_number = $this->numbering->next('supplier_return');
            $sr->save();

            foreach ($data['items'] as $idx => $itemData) {
                $this->createItem($sr, $itemData, $idx);
            }

            $sr->refresh()->recomputeTotals();

            return $sr->refresh();
        });
    }

    /**
     * Update draft.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateDraft(SupplierReturn $sr, array $data, User $by): SupplierReturn
    {
        if (! $sr->canBeEdited()) {
            throw ValidationException::withMessages(['status' => 'SR sudah tidak bisa di-edit.']);
        }

        return DB::transaction(function () use ($sr, $data, $by): SupplierReturn {
            $sr->update([
                'return_date' => $data['return_date'] ?? $sr->return_date,
                'reason_code' => $data['reason_code'] ?? $sr->reason_code,
                'reason_notes' => array_key_exists('reason_notes', $data) ? $data['reason_notes'] : $sr->reason_notes,
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $sr->notes,
                'updated_by' => $by->id,
            ]);

            if (! empty($data['items'])) {
                $sr->items()->delete();
                foreach ($data['items'] as $idx => $itemData) {
                    $this->createItem($sr, $itemData, $idx);
                }
            }

            $sr->refresh()->recomputeTotals();

            return $sr->refresh();
        });
    }

    /**
     * Approve: validasi ulang availability, increment source tracking.
     */
    public function approve(SupplierReturn $sr, User $admin): SupplierReturn
    {
        if (! $sr->canBeApproved()) {
            throw ValidationException::withMessages(['status' => 'SR tidak dalam status draft.']);
        }

        return DB::transaction(function () use ($sr, $admin): SupplierReturn {
            $sr->load('items');

            foreach ($sr->items as $item) {
                $this->validateSourceAvailability($item);
            }

            // Increment tracking di source rows
            foreach ($sr->items as $item) {
                $this->applySourceTracking($item, +1);
            }

            $sr->update([
                'status' => SupplierReturn::STATUS_APPROVED,
                'approved_at' => now(),
                'approved_by' => $admin->id,
                'updated_by' => $admin->id,
            ]);

            return $sr->refresh();
        });
    }

    /**
     * Mark sent. Stock impact hanya untuk source 'stock'.
     */
    public function markSent(SupplierReturn $sr, ?string $sentDate, User $by): SupplierReturn
    {
        if (! $sr->canBeSent()) {
            throw ValidationException::withMessages(['status' => 'SR harus status approved.']);
        }

        return DB::transaction(function () use ($sr, $sentDate, $by): SupplierReturn {
            $sr->load('items.product.baseUnit', 'items.productUnit');

            foreach ($sr->items as $item) {
                if ($item->source_type !== SupplierReturnItem::SOURCE_STOCK) {
                    continue;
                }
                if ($item->batch_id === null) {
                    throw ValidationException::withMessages([
                        "items.{$item->id}" => 'Source stock harus punya batch_id.',
                    ]);
                }

                $baseUnitId = (int) $item->product->base_unit_id;
                $unitPerBase = (int) ($item->productUnit?->qty_to_base ?? 1);
                $costPerBase = $unitPerBase > 0 ? (float) $item->cost_price / $unitPerBase : 0.0;

                $this->stockWriter->writeOut([
                    'product_id' => $item->product_id,
                    'batch_id' => $item->batch_id,
                    'product_unit_id' => $baseUnitId,
                    'type' => StockLedger::TYPE_RETURN_OUT_TO_SUPPLIER,
                    'qty_out' => (int) $item->qty_base,
                    'cost_price' => $costPerBase,
                    'ref_type' => 'SupplierReturn',
                    'ref_id' => $sr->id,
                    'notes' => "Retur Supplier {$sr->return_number}",
                ], $by);
            }

            $sr->update([
                'status' => SupplierReturn::STATUS_SENT,
                'sent_at' => now(),
                'sent_by' => $by->id,
                'sent_date' => $sentDate ?? now()->toDateString(),
                'updated_by' => $by->id,
            ]);

            return $sr->refresh();
        });
    }

    /**
     * Settle dengan nominal kredit dari supplier.
     */
    public function settle(SupplierReturn $sr, float $amount, ?string $notes, User $by): SupplierReturn
    {
        if (! $sr->canBeSettled()) {
            throw ValidationException::withMessages(['status' => 'SR harus status sent.']);
        }
        if ($amount < 0) {
            throw ValidationException::withMessages(['settled_amount' => 'Settled amount tidak boleh negatif.']);
        }

        $sr->update([
            'status' => SupplierReturn::STATUS_SETTLED,
            'settled_at' => now(),
            'settled_by' => $by->id,
            'settled_date' => now()->toDateString(),
            'settled_amount' => $amount,
            'settlement_type' => 'credit',
            'settlement_notes' => $notes,
            'updated_by' => $by->id,
        ]);

        return $sr->refresh();
    }

    /**
     * Cancel. Kalau sudah approved → revert tracking source.
     */
    public function cancel(SupplierReturn $sr, string $reason, User $by): SupplierReturn
    {
        if (! $sr->canBeCancelled()) {
            throw ValidationException::withMessages(['status' => 'SR tidak bisa di-cancel pada status ini.']);
        }

        return DB::transaction(function () use ($sr, $reason, $by): SupplierReturn {
            $wasApproved = $sr->status === SupplierReturn::STATUS_APPROVED;
            if ($wasApproved) {
                $sr->load('items');
                foreach ($sr->items as $item) {
                    $this->applySourceTracking($item, -1);
                }
            }

            $sr->update([
                'status' => SupplierReturn::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by' => $by->id,
                'cancel_reason' => $reason,
                'updated_by' => $by->id,
            ]);

            return $sr->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $itemData
     */
    private function createItem(SupplierReturn $sr, array $itemData, int $sortOrder): SupplierReturnItem
    {
        $sourceType = $itemData['source_type'];
        if (! in_array($sourceType, SupplierReturnItem::SOURCES, true)) {
            throw ValidationException::withMessages(['items' => "source_type tidak valid: {$sourceType}"]);
        }

        $product = Product::query()->findOrFail($itemData['product_id']);
        $unit = ProductUnit::query()->findOrFail($itemData['product_unit_id']);
        $qty = (int) $itemData['qty'];
        if ($qty < 1) {
            throw ValidationException::withMessages(['items' => 'qty minimal 1.']);
        }

        $costPrice = (float) $itemData['cost_price'];
        $perBase = (int) $unit->qty_to_base;

        $batchId = $itemData['batch_id'] ?? null;
        $batchCode = null;
        if ($batchId !== null) {
            $batchCode = ProductBatch::query()->where('id', $batchId)->value('batch_code');
        }

        // Validasi ketersediaan di source
        $this->validateSourceForCreate($sourceType, $itemData, $qty, $sr->supplier_id);

        return SupplierReturnItem::create([
            'supplier_return_id' => $sr->id,
            'source_type' => $sourceType,
            'source_id' => $itemData['source_id'] ?? null,
            'grn_item_id' => $itemData['grn_item_id'] ?? null,
            'customer_return_item_id' => $itemData['customer_return_item_id'] ?? null,
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'product_name_snapshot' => $product->name,
            'product_sku_snapshot' => $product->sku,
            'product_unit_name_snapshot' => $unit->name,
            'batch_id' => $batchId,
            'batch_code_snapshot' => $batchCode,
            'qty' => $qty,
            'qty_base' => $qty * $perBase,
            'cost_price' => $costPrice,
            'line_value' => round($costPrice * $qty, 2),
            'notes' => $itemData['notes'] ?? null,
            'sort_order' => $sortOrder,
        ]);
    }

    /**
     * Validasi source saat create (qty ≤ available).
     *
     * @param  array<string, mixed>  $itemData
     */
    private function validateSourceForCreate(string $sourceType, array $itemData, int $qty, int $supplierId): void
    {
        switch ($sourceType) {
            case SupplierReturnItem::SOURCE_GRN_DAMAGED:
                $grnItem = GrnItem::query()->findOrFail($itemData['grn_item_id'] ?? $itemData['source_id'] ?? 0);
                $supplierIdOfGrn = $grnItem->goodsReceipt?->supplier_id;
                if ($supplierIdOfGrn !== $supplierId) {
                    throw ValidationException::withMessages(['items' => 'GRN tidak milik supplier ini.']);
                }
                $avail = (int) $grnItem->qty_damaged - (int) $grnItem->qty_returned_to_supplier;
                if ($qty > $avail) {
                    throw ValidationException::withMessages(['items' => "Qty melebihi available di GRN (max {$avail})."]);
                }
                break;

            case SupplierReturnItem::SOURCE_CUSTOMER_RETURN_BS:
                $crItem = CustomerReturnItem::query()->findOrFail($itemData['customer_return_item_id'] ?? $itemData['source_id'] ?? 0);
                $avail = (int) $crItem->qty_bs - (int) $crItem->qty_returned_to_supplier;
                if ($qty > $avail) {
                    throw ValidationException::withMessages(['items' => "Qty melebihi available di CR BS (max {$avail})."]);
                }
                break;

            case SupplierReturnItem::SOURCE_STOCK:
                if (empty($itemData['batch_id'])) {
                    throw ValidationException::withMessages(['items' => 'Source stock harus pilih batch.']);
                }
                $available = (int) (StockBalance::query()
                    ->where('product_id', $itemData['product_id'])
                    ->where('batch_id', $itemData['batch_id'])
                    ->value('qty_on_hand') ?? 0);
                $unit = ProductUnit::query()->findOrFail($itemData['product_unit_id']);
                $qtyBase = $qty * (int) $unit->qty_to_base;
                if ($qtyBase > $available) {
                    throw ValidationException::withMessages(['items' => "Qty melebihi stock available (max {$available} base)."]);
                }
                break;
        }
    }

    /**
     * Re-validate at approve time.
     */
    private function validateSourceAvailability(SupplierReturnItem $item): void
    {
        switch ($item->source_type) {
            case SupplierReturnItem::SOURCE_GRN_DAMAGED:
                $grnItem = GrnItem::query()->findOrFail($item->grn_item_id);
                $avail = (int) $grnItem->qty_damaged - (int) $grnItem->qty_returned_to_supplier;
                if ((int) $item->qty > $avail) {
                    throw ValidationException::withMessages([
                        "items.{$item->id}" => "Available di GRN tinggal {$avail}, qty: {$item->qty}.",
                    ]);
                }
                break;

            case SupplierReturnItem::SOURCE_CUSTOMER_RETURN_BS:
                $crItem = CustomerReturnItem::query()->findOrFail($item->customer_return_item_id);
                $avail = (int) $crItem->qty_bs - (int) $crItem->qty_returned_to_supplier;
                if ((int) $item->qty > $avail) {
                    throw ValidationException::withMessages([
                        "items.{$item->id}" => "Available di CR tinggal {$avail}, qty: {$item->qty}.",
                    ]);
                }
                break;

            case SupplierReturnItem::SOURCE_STOCK:
                $bal = (int) (StockBalance::query()
                    ->where('product_id', $item->product_id)
                    ->where('batch_id', $item->batch_id)
                    ->value('qty_on_hand') ?? 0);
                if ((int) $item->qty_base > $bal) {
                    throw ValidationException::withMessages([
                        "items.{$item->id}" => "Stock tidak cukup (available {$bal}).",
                    ]);
                }
                break;
        }
    }

    /**
     * Increment (+1) atau decrement (-1) qty_returned_to_supplier di source row.
     */
    private function applySourceTracking(SupplierReturnItem $item, int $sign): void
    {
        $delta = $sign * (int) $item->qty;

        switch ($item->source_type) {
            case SupplierReturnItem::SOURCE_GRN_DAMAGED:
                GrnItem::query()
                    ->where('id', $item->grn_item_id)
                    ->update(['qty_returned_to_supplier' => DB::raw("qty_returned_to_supplier + ({$delta})")]);
                break;

            case SupplierReturnItem::SOURCE_CUSTOMER_RETURN_BS:
                CustomerReturnItem::query()
                    ->where('id', $item->customer_return_item_id)
                    ->update(['qty_returned_to_supplier' => DB::raw("qty_returned_to_supplier + ({$delta})")]);
                break;

                // stock/adjustment: tracking via stock_ledger nanti di markSent
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSupplierSnapshot(Supplier $s): array
    {
        return [
            'id' => $s->id,
            'code' => $s->code,
            'name' => $s->name,
            'phone' => $s->phone,
            'address' => $s->address,
            'city' => $s->city,
            'snapshotted_at' => now()->toIso8601String(),
        ];
    }
}
