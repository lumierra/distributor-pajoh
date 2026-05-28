<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreSupplierProductRequest;
use App\Models\Product;
use App\Models\SupplierProduct;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProductSupplierController extends Controller
{
    public function store(StoreSupplierProductRequest $request, Product $product): RedirectResponse
    {
        SupplierProduct::create(array_merge($request->validated(), [
            'product_id' => $product->id,
        ]));

        return back()->with('flash.success', 'Supplier ditautkan ke produk.');
    }

    public function update(Request $request, SupplierProduct $supplierProduct): RedirectResponse
    {
        $this->authorize('update', $supplierProduct->product);

        $data = $request->validate([
            'supplier_sku' => ['nullable', 'string', 'max:64'],
            'moq' => ['nullable', 'integer', 'min:1'],
            'is_primary' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $supplierProduct->update($data);

        return back()->with('flash.success', 'Data supplier produk diperbarui.');
    }

    public function destroy(SupplierProduct $supplierProduct): RedirectResponse
    {
        $this->authorize('update', $supplierProduct->product);

        $supplierProduct->delete();

        return back()->with('flash.success', 'Supplier dilepas dari produk.');
    }
}
