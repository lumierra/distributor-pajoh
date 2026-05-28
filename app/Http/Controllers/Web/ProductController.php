<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Supplier;
use App\Models\Unit;
use App\Services\Product\ProductService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function __construct(private readonly ProductService $service) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Product::class);

        $query = Product::query()
            ->with(['category:id,code,name', 'baseUnit:id,product_id,name'])
            ->orderBy('name');

        if ($search = trim((string) $request->input('q'))) {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%");
            });
        }

        if ($cat = $request->input('category')) {
            $query->withCategory($cat);
        }

        if ($request->filled('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        $totals = Product::query()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active')
            ->selectRaw('SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) AS inactive')
            ->first();

        return Inertia::render('Products/Index', [
            'products' => $query->paginate(25)->withQueryString(),
            'categories' => ProductCategory::query()->active()->orderBy('sort_order')->get(['id', 'code', 'name']),
            'filters' => [
                'q' => $request->input('q'),
                'category' => $request->input('category'),
                'active' => $request->input('active'),
            ],
            'stats' => [
                'total' => (int) ($totals->total ?? 0),
                'active' => (int) ($totals->active ?? 0),
                'inactive' => (int) ($totals->inactive ?? 0),
                'categories' => ProductCategory::query()->count(),
            ],
        ]);
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $units = $data['units'];
        unset($data['units']);

        $product = $this->service->create($data, $units);

        return redirect()
            ->route('products.show', $product)
            ->with('flash.success', "Produk {$product->name} ({$product->sku}) berhasil dibuat.");
    }

    public function show(Product $product): Response
    {
        $this->authorize('view', $product);

        $product->load([
            'category:id,code,name',
            'baseUnit',
            'units.unit:id,name',
            'supplierProductUnits.supplier:id,code,name',
            'supplierProductUnits.productUnit:id,name,level',
            'supplierProducts.supplier:id,code,name',
        ]);

        return Inertia::render('Products/Show', [
            'product' => $product,
            'categories' => ProductCategory::query()->active()->orderBy('sort_order')->get(['id', 'code', 'name']),
            'units' => Unit::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'suppliers' => Supplier::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
            'canUpdate' => request()->user()?->can('update', $product) ?? false,
            'canDelete' => request()->user()?->can('delete', $product) ?? false,
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $this->service->update($product, $request->validated());

        return back()->with('flash.success', 'Produk diperbarui.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->authorize('delete', $product);

        $product->delete();

        return redirect()
            ->route('products.index')
            ->with('flash.success', "Produk {$product->name} dihapus.");
    }

    public function toggleActive(Product $product): RedirectResponse
    {
        $this->authorize('toggleActive', $product);

        $this->service->toggleActive($product);

        return back()->with(
            'flash.success',
            $product->is_active ? 'Produk diaktifkan.' : 'Produk dinonaktifkan.',
        );
    }
}
