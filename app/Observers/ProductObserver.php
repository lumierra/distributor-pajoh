<?php

namespace App\Observers;

use App\Models\Product;
use App\Services\Product\ProductService;

/**
 * Cache invalidation untuk `products:active` setelah setiap mutasi.
 *
 * Activity log auto-captured via trait `HasActivityLog` di Product model.
 */
class ProductObserver
{
    public function created(Product $product): void
    {
        $this->invalidate();
    }

    public function updated(Product $product): void
    {
        $this->invalidate();
    }

    public function deleted(Product $product): void
    {
        $this->invalidate();
    }

    public function restored(Product $product): void
    {
        $this->invalidate();
    }

    private function invalidate(): void
    {
        app(ProductService::class)->invalidateCache();
    }
}
