<?php

namespace App\Observers;

use App\Models\Supplier;
use App\Services\Supplier\SupplierService;

/**
 * Auto-invalidate cache `suppliers:active` setiap mutasi.
 *
 * Activity log auto-captured via trait `HasActivityLog` di model Supplier.
 */
class SupplierObserver
{
    public function created(Supplier $supplier): void
    {
        $this->invalidate();
    }

    public function updated(Supplier $supplier): void
    {
        $this->invalidate();
    }

    public function deleted(Supplier $supplier): void
    {
        $this->invalidate();
    }

    public function restored(Supplier $supplier): void
    {
        $this->invalidate();
    }

    private function invalidate(): void
    {
        app(SupplierService::class)->invalidateCache();
    }
}
