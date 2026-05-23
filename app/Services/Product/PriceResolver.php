<?php

namespace App\Services\Product;

use App\Models\Product;
use App\Models\ProductPrice;

/**
 * Resolve price untuk kombinasi `product × unit × tier`.
 * Fallback ke KCL kalau price untuk unit yang diminta tidak ada
 * (mis. produk hanya punya KCL, tapi user pilih BSR di SO).
 */
class PriceResolver
{
    public function priceFor(Product $product, int $unitId, int $tierId): float
    {
        $row = ProductPrice::query()
            ->where('product_id', $product->id)
            ->where('product_unit_id', $unitId)
            ->where('price_tier_id', $tierId)
            ->first();

        if ($row) {
            return (float) $row->price;
        }

        // Fallback: ambil KCL kalau ada, kalikan qty_to_base unit target.
        $product->loadMissing('baseUnit');
        if ($product->base_unit_id === null) {
            return 0.0;
        }
        $kclRow = ProductPrice::query()
            ->where('product_id', $product->id)
            ->where('product_unit_id', $product->base_unit_id)
            ->where('price_tier_id', $tierId)
            ->first();

        if (! $kclRow) {
            return 0.0;
        }

        $unit = $product->units->firstWhere('id', $unitId);
        $multiplier = $unit ? (int) $unit->qty_to_base : 1;

        return (float) $kclRow->price * $multiplier;
    }
}
