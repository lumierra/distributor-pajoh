<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreUnitRequest;
use App\Models\Product;
use App\Models\ProductUnit;
use Illuminate\Http\RedirectResponse;

class ProductUnitController extends Controller
{
    /**
     * Tambah satuan baru ke produk existing (pivot product_units).
     * Harga di-set via tab Supplier & Harga.
     */
    public function store(StoreUnitRequest $request, Product $product): RedirectResponse
    {
        $product->units()->create($request->validated());

        return back()->with('flash.success', 'Unit baru ditambahkan.');
    }

    public function destroy(ProductUnit $unit): RedirectResponse
    {
        $this->authorize('update', $unit->product);

        if ((int) $unit->qty_to_base === 1) {
            return back()->withErrors(['delete' => 'Satuan base unit (qty=1) tidak bisa dihapus.']);
        }

        $unit->delete();

        return back()->with('flash.success', 'Unit dihapus.');
    }
}
