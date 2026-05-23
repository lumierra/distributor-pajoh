<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ProductCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductCategoryController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', ProductCategory::class);

        return Inertia::render('Products/Categories', [
            'categories' => ProductCategory::query()
                ->withCount('products')
                ->orderBy('sort_order')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', ProductCategory::class);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:16', 'regex:/^[A-Z_][A-Z0-9_]*$/', 'unique:product_categories,code'],
            'name' => ['required', 'string', 'max:64'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        ProductCategory::create($data);

        return back()->with('flash.success', 'Kategori produk dibuat.');
    }

    public function update(Request $request, ProductCategory $productCategory): RedirectResponse
    {
        $this->authorize('update', $productCategory);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:64'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $productCategory->update($data);

        return back()->with('flash.success', 'Kategori diperbarui.');
    }

    public function destroy(ProductCategory $productCategory): RedirectResponse
    {
        $this->authorize('delete', $productCategory);

        $productCategory->delete();

        return back()->with('flash.success', 'Kategori dihapus.');
    }
}
