<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\UpdatePricesRequest;
use App\Models\Product;
use App\Models\ProductPrice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class ProductPriceController extends Controller
{
    /**
     * Bulk update price matrix untuk produk tertentu.
     * Observer ProductPriceObserver auto-insert ke product_price_history.
     */
    public function update(UpdatePricesRequest $request, Product $product): RedirectResponse
    {
        DB::transaction(function () use ($request, $product): void {
            foreach ($request->validated('prices') as $row) {
                // Pakai model save() supaya `ProductPriceObserver::updated()`
                // ter-trigger dan insert ke `product_price_history`.
                $price = ProductPrice::query()
                    ->where('product_id', $product->id)
                    ->where('product_unit_id', $row['product_unit_id'])
                    ->where('price_tier_id', $row['price_tier_id'])
                    ->first();

                if ($price === null) {
                    ProductPrice::create([
                        'product_id' => $product->id,
                        'product_unit_id' => $row['product_unit_id'],
                        'price_tier_id' => $row['price_tier_id'],
                        'price' => $row['price'],
                    ]);

                    continue;
                }

                $price->price = $row['price'];
                $price->save();
            }
        });

        return back()->with('flash.success', 'Harga produk diperbarui.');
    }
}
