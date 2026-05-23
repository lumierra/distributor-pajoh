<?php

namespace App\Observers;

use App\Models\ProductPrice;
use App\Models\ProductPriceHistory;

/**
 * Auto-insert ke `product_price_history` setiap kali `product_prices.price` berubah.
 */
class ProductPriceObserver
{
    public function updated(ProductPrice $price): void
    {
        if (! $price->wasChanged('price')) {
            return;
        }

        ProductPriceHistory::create([
            'product_id' => $price->product_id,
            'product_unit_id' => $price->product_unit_id,
            'price_tier_id' => $price->price_tier_id,
            'old_price' => $price->getOriginal('price'),
            'new_price' => $price->price,
            'change_reason' => ProductPriceHistory::REASON_MANUAL,
            'changed_by' => auth()->id(),
            'changed_at' => now(),
        ]);
    }
}
