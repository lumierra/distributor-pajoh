<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreUnitRequest;
use App\Models\PriceTier;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\ProductUnit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class ProductUnitController extends Controller
{
    /**
     * Tambah UoM baru ke produk existing. Otomatis generate price 0
     * untuk semua price tier aktif.
     */
    public function store(StoreUnitRequest $request, Product $product): RedirectResponse
    {
        DB::transaction(function () use ($request, $product): void {
            $unit = $product->units()->create($request->validated());

            foreach (PriceTier::query()->active()->get() as $tier) {
                ProductPrice::create([
                    'product_id' => $product->id,
                    'product_unit_id' => $unit->id,
                    'price_tier_id' => $tier->id,
                    'price' => 0,
                ]);
            }
        });

        return back()->with('flash.success', 'Unit baru ditambahkan.');
    }

    public function destroy(ProductUnit $unit): RedirectResponse
    {
        $this->authorize('update', $unit->product);

        if ($unit->level === ProductUnit::LEVEL_KCL) {
            return back()->withErrors(['delete' => 'Unit KCL (base) tidak bisa dihapus.']);
        }

        $unit->delete();

        return back()->with('flash.success', 'Unit dihapus.');
    }
}
