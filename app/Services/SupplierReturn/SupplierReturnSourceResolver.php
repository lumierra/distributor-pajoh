<?php

namespace App\Services\SupplierReturn;

use App\Models\CustomerReturn;
use App\Models\CustomerReturnItem;
use App\Models\GoodsReceipt;
use App\Models\GrnItem;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\Supplier;
use Illuminate\Support\Collection;

/**
 * Resolve item-item yang available untuk di-claim ke supplier dari
 * berbagai sumber: GRN damaged, Customer Return BS, Stock.
 *
 * (Adjustment source di-defer sampai T09 Adjustment selesai.)
 */
class SupplierReturnSourceResolver
{
    /**
     * GRN items dgn qty_damaged > qty_returned_to_supplier dari supplier ini.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function fromGrnDamaged(Supplier $supplier): Collection
    {
        return GrnItem::query()
            ->whereHas('goodsReceipt', fn ($q) => $q
                ->where('supplier_id', $supplier->id)
                ->where('status', GoodsReceipt::STATUS_POSTED))
            ->whereColumn('qty_damaged', '>', 'qty_returned_to_supplier')
            ->with(['product:id,name,sku', 'productUnit:id,name,qty_to_base', 'batch:id,batch_code'])
            ->get()
            ->map(fn (GrnItem $i): array => [
                'source_type' => 'grn_damaged',
                'source_id' => $i->id,
                'grn_item_id' => $i->id,
                'product_id' => $i->product_id,
                'product_unit_id' => $i->product_unit_id,
                'product_name' => $i->product_name_snapshot,
                'product_sku' => $i->product_sku_snapshot,
                'product_unit_name' => $i->product_unit_name_snapshot,
                'batch_id' => $i->batch_id,
                'batch_code' => $i->batch_code,
                'available_qty' => (int) $i->qty_damaged - (int) $i->qty_returned_to_supplier,
                'cost_price' => (float) $i->cost_price,
            ]);
    }

    /**
     * Customer return items dgn qty_bs > qty_returned_to_supplier, dari
     * produk yang di-supply oleh supplier ini.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function fromCustomerReturnBs(Supplier $supplier): Collection
    {
        $supplierProductIds = Product::query()
            ->where('supplier_id', $supplier->id)
            ->pluck('id')
            ->all();
        if (empty($supplierProductIds)) {
            return collect();
        }

        return CustomerReturnItem::query()
            ->whereHas('customerReturn', fn ($q) => $q
                ->whereIn('status', [CustomerReturn::STATUS_POSTED, CustomerReturn::STATUS_CREDITED]))
            ->whereIn('product_id', $supplierProductIds)
            ->whereColumn('qty_bs', '>', 'qty_returned_to_supplier')
            ->with(['product:id,name,sku', 'productUnit:id,name,qty_to_base', 'batch:id,batch_code', 'customerReturn:id,return_number'])
            ->get()
            ->map(fn (CustomerReturnItem $i): array => [
                'source_type' => 'customer_return_bs',
                'source_id' => $i->id,
                'customer_return_item_id' => $i->id,
                'customer_return_number' => $i->customerReturn?->return_number,
                'product_id' => $i->product_id,
                'product_unit_id' => $i->product_unit_id,
                'product_name' => $i->product_name_snapshot,
                'product_sku' => $i->product_sku_snapshot,
                'product_unit_name' => $i->product_unit_name_snapshot,
                'batch_id' => $i->batch_id,
                'batch_code' => $i->batch_code_snapshot,
                'available_qty' => (int) $i->qty_bs - (int) $i->qty_returned_to_supplier,
                'cost_price' => (float) $i->unit_price,
            ]);
    }

    /**
     * Stock balance untuk batch dari supplier ini.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function fromStock(Supplier $supplier): Collection
    {
        return StockBalance::query()
            ->where('qty_on_hand', '>', 0)
            ->whereHas('batch', fn ($q) => $q->where('supplier_id', $supplier->id))
            ->with(['product:id,name,sku,base_unit_id', 'product.baseUnit:id,name,qty_to_base', 'batch:id,batch_code,expired_date'])
            ->get()
            ->map(fn (StockBalance $b): array => [
                'source_type' => 'stock',
                'source_id' => null,
                'product_id' => $b->product_id,
                'product_unit_id' => $b->product?->base_unit_id,
                'product_name' => $b->product?->name,
                'product_sku' => $b->product?->sku,
                'product_unit_name' => $b->product?->baseUnit?->name,
                'batch_id' => $b->batch_id,
                'batch_code' => $b->batch?->batch_code,
                'expired_date' => $b->batch?->expired_date?->toDateString(),
                'available_qty' => (int) $b->qty_on_hand,
                'cost_price' => 0.0, // user-input untuk stock source
            ]);
    }
}
