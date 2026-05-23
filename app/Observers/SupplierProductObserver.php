<?php

namespace App\Observers;

use App\Models\SupplierProduct;
use Illuminate\Support\Facades\DB;

/**
 * Enforce: hanya 1 `is_primary=true` per produk.
 * Saat set baru ke true → auto-unset primary lama di produk yang sama.
 */
class SupplierProductObserver
{
    public function creating(SupplierProduct $row): void
    {
        if ($row->is_primary) {
            $this->unsetOtherPrimaries($row->product_id, $row->id);
        }
    }

    public function updating(SupplierProduct $row): void
    {
        if ($row->is_primary && $row->isDirty('is_primary')) {
            $this->unsetOtherPrimaries($row->product_id, $row->id);
        }
    }

    private function unsetOtherPrimaries(int $productId, ?int $excludeId): void
    {
        DB::table('supplier_products')
            ->where('product_id', $productId)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->where('is_primary', true)
            ->update(['is_primary' => false]);
    }
}
