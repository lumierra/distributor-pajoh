<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\SupplierProductUnit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * CRUD pricing baris (supplier × produk × satuan) di tab "Supplier & Harga"
 * halaman detail produk.
 */
class SupplierProductUnitController extends Controller
{
    public function store(Request $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $data = $request->validate([
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'product_unit_id' => ['required', 'integer', 'exists:product_units,id'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'sell_price' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        SupplierProductUnit::updateOrCreate(
            [
                'product_id' => $product->id,
                'supplier_id' => $data['supplier_id'],
                'product_unit_id' => $data['product_unit_id'],
            ],
            [
                'cost_price' => $data['cost_price'] ?? 0,
                'sell_price' => $data['sell_price'] ?? 0,
                'is_active' => $data['is_active'] ?? true,
            ],
        );

        return back()->with('flash.success', 'Harga supplier disimpan.');
    }

    public function update(Request $request, SupplierProductUnit $supplierProductUnit): RedirectResponse
    {
        $this->authorize('update', $supplierProductUnit->product);

        $data = $request->validate([
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'sell_price' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $supplierProductUnit->update($data);

        return back()->with('flash.success', 'Harga supplier diperbarui.');
    }

    public function destroy(SupplierProductUnit $supplierProductUnit): RedirectResponse
    {
        $this->authorize('update', $supplierProductUnit->product);

        $supplierProductUnit->delete();

        return back()->with('flash.success', 'Harga supplier dihapus.');
    }
}
