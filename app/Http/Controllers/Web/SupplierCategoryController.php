<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\SupplierCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupplierCategoryController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', SupplierCategory::class);

        return Inertia::render('Suppliers/Categories', [
            'categories' => SupplierCategory::query()
                ->withCount('suppliers')
                ->orderBy('sort_order')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', SupplierCategory::class);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:32', 'regex:/^[A-Z_][A-Z0-9_]*$/', 'unique:supplier_categories,code'],
            'name' => ['required', 'string', 'max:64'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        SupplierCategory::create($data);

        return back()->with('flash.success', 'Kategori supplier dibuat.');
    }

    public function update(Request $request, SupplierCategory $supplierCategory): RedirectResponse
    {
        $this->authorize('update', $supplierCategory);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:64'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $supplierCategory->update($data);

        return back()->with('flash.success', 'Kategori diperbarui.');
    }

    public function destroy(SupplierCategory $supplierCategory): RedirectResponse
    {
        $this->authorize('delete', $supplierCategory);

        $supplierCategory->delete();

        return back()->with('flash.success', 'Kategori dihapus.');
    }
}
