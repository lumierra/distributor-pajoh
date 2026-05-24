<?php

namespace App\Services\CustomerReturn;

use App\Models\Customer;
use App\Models\CustomerReturn;
use App\Models\CustomerReturnItem;
use App\Models\DeliveryOrder;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductUnit;
use App\Models\StockLedger;
use App\Models\User;
use App\Services\Inventory\StockLedgerWriter;
use App\Services\Numbering\NumberingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerReturnService
{
    public function __construct(
        private readonly NumberingService $numbering,
        private readonly StockLedgerWriter $stockWriter,
        private readonly CreditNoteService $creditNoteService,
    ) {}

    /**
     * Create manual customer return draft (web/mobile).
     *
     * @param  array{
     *   customer_id:int, return_date:string|\DateTimeInterface,
     *   invoice_id?:int|null, delivery_order_id?:int|null,
     *   sales_id?:int|null, brand_tag?:string|null,
     *   reason_code?:string|null, reason_notes?:string|null,
     *   notes?:string|null, proof_photo_path?:string|null,
     *   items: array<int, array{
     *     product_id:int, product_unit_id:int, qty_total:int,
     *     qty_good?:int, qty_bs?:int, unit_price:float,
     *     batch_id?:int|null, invoice_item_id?:int|null,
     *     do_item_id?:int|null, so_item_id?:int|null, notes?:string|null
     *   }>
     * }  $data
     */
    public function createDraft(array $data, User $by): CustomerReturn
    {
        return DB::transaction(function () use ($data, $by): CustomerReturn {
            /** @var Customer $customer */
            $customer = Customer::query()->findOrFail($data['customer_id']);

            $cr = new CustomerReturn([
                'customer_id' => $customer->id,
                'customer_snapshot' => $this->buildCustomerSnapshot($customer),
                'sales_id' => $data['sales_id'] ?? null,
                'invoice_id' => $data['invoice_id'] ?? null,
                'delivery_order_id' => $data['delivery_order_id'] ?? null,
                'return_date' => $data['return_date'],
                'brand_tag' => $data['brand_tag'] ?? null,
                'reason_code' => $data['reason_code'] ?? null,
                'reason_notes' => $data['reason_notes'] ?? null,
                'status' => CustomerReturn::STATUS_DRAFT,
                'fiscal_year' => (int) now()->parse($data['return_date'])->format('Y'),
                'notes' => $data['notes'] ?? null,
                'proof_photo_path' => $data['proof_photo_path'] ?? null,
                'created_by' => $by->id,
            ]);
            $cr->return_number = $this->numbering->next('customer_return');
            $cr->save();

            foreach ($data['items'] as $idx => $itemData) {
                $this->createItem($cr, $itemData, $idx);
            }

            $cr->refresh()->recomputeTotals();

            return $cr->refresh();
        });
    }

    /**
     * Update draft CR (header + replace items).
     *
     * @param  array<string, mixed>  $data
     */
    public function updateDraft(CustomerReturn $cr, array $data, User $by): CustomerReturn
    {
        if (! $cr->canBeEdited()) {
            throw ValidationException::withMessages(['status' => 'CR sudah tidak bisa di-edit.']);
        }

        return DB::transaction(function () use ($cr, $data, $by): CustomerReturn {
            $cr->update([
                'invoice_id' => array_key_exists('invoice_id', $data) ? $data['invoice_id'] : $cr->invoice_id,
                'delivery_order_id' => array_key_exists('delivery_order_id', $data) ? $data['delivery_order_id'] : $cr->delivery_order_id,
                'return_date' => $data['return_date'] ?? $cr->return_date,
                'brand_tag' => array_key_exists('brand_tag', $data) ? $data['brand_tag'] : $cr->brand_tag,
                'reason_code' => array_key_exists('reason_code', $data) ? $data['reason_code'] : $cr->reason_code,
                'reason_notes' => array_key_exists('reason_notes', $data) ? $data['reason_notes'] : $cr->reason_notes,
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $cr->notes,
                'updated_by' => $by->id,
            ]);

            if (! empty($data['items'])) {
                $cr->items()->delete();
                foreach ($data['items'] as $idx => $itemData) {
                    $this->createItem($cr, $itemData, $idx);
                }
            }

            $cr->refresh()->recomputeTotals();

            return $cr->refresh();
        });
    }

    /**
     * Auto-create CR dari DO partial returned. Status langsung 'sorted',
     * default qty_bs = qty_returned (anggap rusak). Idempotent — kalau sudah
     * ada CR untuk DO ini, return existing.
     */
    public function createFromDeliveryReject(DeliveryOrder $do, ?User $by = null): CustomerReturn
    {
        $existing = CustomerReturn::query()
            ->where('delivery_order_id', $do->id)
            ->first();
        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($do, $by): CustomerReturn {
            $do->loadMissing(['items.soItem', 'salesOrder.customer']);
            $customer = $do->salesOrder->customer;

            $cr = new CustomerReturn([
                'customer_id' => $customer->id,
                'customer_snapshot' => $do->salesOrder->customer_snapshot ?? $this->buildCustomerSnapshot($customer),
                'sales_id' => $do->salesOrder->sales_id,
                'invoice_id' => null,
                'delivery_order_id' => $do->id,
                'return_date' => now()->toDateString(),
                'reason_code' => 'damaged',
                'status' => CustomerReturn::STATUS_SORTED,
                'fiscal_year' => (int) now()->format('Y'),
                'sorted_at' => now(),
                'sorted_by' => $by?->id,
                'created_by' => $by?->id,
            ]);
            $cr->return_number = $this->numbering->next('customer_return');
            $cr->save();

            $sort = 0;
            foreach ($do->items as $doItem) {
                if ((int) $doItem->qty_returned <= 0) {
                    continue;
                }

                $unit = ProductUnit::find($doItem->product_unit_id);
                $unitNet = (float) ($doItem->soItem->unit_net_price ?? 0);
                $qty = (int) $doItem->qty_returned;

                CustomerReturnItem::create([
                    'customer_return_id' => $cr->id,
                    'do_item_id' => $doItem->id,
                    'so_item_id' => $doItem->so_item_id,
                    'product_id' => $doItem->product_id,
                    'product_unit_id' => $doItem->product_unit_id,
                    'product_name_snapshot' => $doItem->product_name_snapshot,
                    'product_sku_snapshot' => $doItem->product_sku_snapshot,
                    'product_unit_name_snapshot' => $doItem->product_unit_name_snapshot,
                    'batch_id' => $doItem->batch_id,
                    'batch_code_snapshot' => $doItem->batch_code_snapshot,
                    'qty_total' => $qty,
                    'qty_good' => 0,
                    'qty_bs' => $qty,
                    'qty_total_base' => $qty * (int) ($unit?->qty_to_base ?? 1),
                    'qty_good_base' => 0,
                    'qty_bs_base' => $qty * (int) ($unit?->qty_to_base ?? 1),
                    'unit_price' => $unitNet,
                    'line_value' => round($unitNet * $qty, 2),
                    'sort_order' => $sort++,
                ]);
            }

            $cr->refresh()->recomputeTotals();

            return $cr->refresh();
        });
    }

    /**
     * Update sortir (qty_good + qty_bs per item).
     *
     * @param  array<int, array{id:int, qty_good:int, qty_bs:int}>  $itemSorts
     */
    public function updateSortResult(CustomerReturn $cr, array $itemSorts, User $by): CustomerReturn
    {
        if (! $cr->canBeSorted()) {
            throw ValidationException::withMessages(['status' => 'CR tidak dapat di-sort.']);
        }

        return DB::transaction(function () use ($cr, $itemSorts, $by): CustomerReturn {
            $cr->load('items.productUnit');

            $byId = collect($itemSorts)->keyBy(fn ($x) => (int) $x['id']);

            foreach ($cr->items as $item) {
                $sort = $byId->get($item->id);
                if ($sort === null) {
                    continue;
                }
                $qtyGood = (int) ($sort['qty_good'] ?? 0);
                $qtyBs = (int) ($sort['qty_bs'] ?? 0);

                if ($qtyGood + $qtyBs !== (int) $item->qty_total) {
                    throw ValidationException::withMessages([
                        "items.{$item->id}" => 'qty_good + qty_bs harus sama dengan qty_total.',
                    ]);
                }

                $perBase = (int) $item->productUnit->qty_to_base;
                $item->update([
                    'qty_good' => $qtyGood,
                    'qty_bs' => $qtyBs,
                    'qty_total_base' => (int) $item->qty_total * $perBase,
                    'qty_good_base' => $qtyGood * $perBase,
                    'qty_bs_base' => $qtyBs * $perBase,
                ]);
            }

            $cr->update([
                'status' => CustomerReturn::STATUS_SORTED,
                'sorted_at' => now(),
                'sorted_by' => $by->id,
                'updated_by' => $by->id,
            ]);

            $cr->refresh()->recomputeTotals();

            return $cr->refresh();
        });
    }

    /**
     * Posting: write stock_ledger return_in untuk qty_good, generate CN,
     * auto-apply ke invoice kalau linked.
     */
    public function post(CustomerReturn $cr, User $by): CustomerReturn
    {
        if (! $cr->canBePosted()) {
            throw ValidationException::withMessages(['status' => 'CR harus status sorted untuk di-post.']);
        }

        return DB::transaction(function () use ($cr, $by): CustomerReturn {
            $cr->load('items.productUnit', 'items.product.baseUnit');

            foreach ($cr->items as $item) {
                if ((int) $item->qty_good_base <= 0) {
                    continue;
                }

                $batchId = $item->batch_id ?? $this->getOrCreateReturnPoolBatchId($item->product_id);
                $baseUnitId = (int) $item->product->base_unit_id;

                $this->stockWriter->writeIn([
                    'product_id' => $item->product_id,
                    'batch_id' => $batchId,
                    'product_unit_id' => $baseUnitId,
                    'type' => StockLedger::TYPE_RETURN_IN,
                    'qty_in' => (int) $item->qty_good_base,
                    'cost_price' => 0,
                    'ref_type' => 'CustomerReturn',
                    'ref_id' => $cr->id,
                    'notes' => "Retur {$cr->return_number}",
                ], $by);
            }

            $cn = $this->creditNoteService->createFromReturn($cr, $by);

            $cr->update([
                'status' => CustomerReturn::STATUS_POSTED,
                'posted_at' => now(),
                'posted_by' => $by->id,
                'credit_note_id' => $cn->id,
                'updated_by' => $by->id,
            ]);

            // Auto-apply ke invoice yg ke-link kalau ada
            if ($cr->invoice_id !== null) {
                $invoice = Invoice::query()->find($cr->invoice_id);
                if ($invoice !== null && (float) $invoice->outstanding > 0) {
                    $applyAmount = min((float) $cn->remaining_amount, (float) $invoice->outstanding);
                    if ($applyAmount > 0) {
                        $this->creditNoteService->applyToInvoice($cn->refresh(), $invoice, $applyAmount, $by);
                    }
                }
            }

            return $cr->refresh();
        });
    }

    public function cancel(CustomerReturn $cr, string $reason, User $by): CustomerReturn
    {
        if (! $cr->canBeCancelled()) {
            throw ValidationException::withMessages(['status' => 'CR tidak dapat di-cancel di status ini.']);
        }

        $cr->update([
            'status' => CustomerReturn::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancelled_by' => $by->id,
            'cancel_reason' => $reason,
            'updated_by' => $by->id,
        ]);

        return $cr->refresh();
    }

    /**
     * @param  array<string, mixed>  $itemData
     */
    private function createItem(CustomerReturn $cr, array $itemData, int $sortOrder): CustomerReturnItem
    {
        $product = Product::query()->findOrFail($itemData['product_id']);
        $unit = ProductUnit::query()->findOrFail($itemData['product_unit_id']);

        $qtyTotal = (int) $itemData['qty_total'];
        $qtyGood = (int) ($itemData['qty_good'] ?? 0);
        $qtyBs = (int) ($itemData['qty_bs'] ?? 0);

        if ($qtyGood + $qtyBs > $qtyTotal) {
            throw ValidationException::withMessages([
                'items' => 'qty_good + qty_bs tidak boleh > qty_total.',
            ]);
        }

        $unitPrice = (float) $itemData['unit_price'];
        $perBase = (int) $unit->qty_to_base;

        $batchCodeSnapshot = null;
        if (! empty($itemData['batch_id'])) {
            $batchCodeSnapshot = ProductBatch::query()->where('id', $itemData['batch_id'])->value('batch_code');
        }

        return CustomerReturnItem::create([
            'customer_return_id' => $cr->id,
            'invoice_item_id' => $itemData['invoice_item_id'] ?? null,
            'do_item_id' => $itemData['do_item_id'] ?? null,
            'so_item_id' => $itemData['so_item_id'] ?? null,
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'product_name_snapshot' => $product->name,
            'product_sku_snapshot' => $product->sku,
            'product_unit_name_snapshot' => $unit->name,
            'batch_id' => $itemData['batch_id'] ?? null,
            'batch_code_snapshot' => $batchCodeSnapshot,
            'qty_total' => $qtyTotal,
            'qty_good' => $qtyGood,
            'qty_bs' => $qtyBs,
            'qty_total_base' => $qtyTotal * $perBase,
            'qty_good_base' => $qtyGood * $perBase,
            'qty_bs_base' => $qtyBs * $perBase,
            'unit_price' => $unitPrice,
            'line_value' => round($unitPrice * $qtyTotal, 2),
            'notes' => $itemData['notes'] ?? null,
            'sort_order' => $sortOrder,
        ]);
    }

    /**
     * Resolve or create a "return pool" batch untuk produk yang batch-nya
     * tidak bisa di-trace saat retur. Satu pool per produk.
     */
    private function getOrCreateReturnPoolBatchId(int $productId): int
    {
        $code = 'RETURN-POOL-'.$productId;

        $batch = ProductBatch::query()
            ->where('product_id', $productId)
            ->where('batch_code', $code)
            ->first();

        if ($batch !== null) {
            return $batch->id;
        }

        return ProductBatch::create([
            'product_id' => $productId,
            'batch_code' => $code,
            'production_date' => null,
            'expired_date' => null,
            'initial_qty_base' => 0,
            'is_active' => true,
            'notes' => 'Auto-created return pool batch',
        ])->id;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCustomerSnapshot(Customer $c): array
    {
        return [
            'id' => $c->id,
            'code' => $c->code,
            'name' => $c->name,
            'phone' => $c->phone,
            'address' => $c->address,
            'city' => $c->city,
            'snapshotted_at' => now()->toIso8601String(),
        ];
    }
}
