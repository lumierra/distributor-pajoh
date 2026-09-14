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
use Illuminate\Http\JsonResponse;
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
                    ->orWhere('sku', 'like', "%{$search}%");
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
            'unitsMaster' => Unit::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
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
        $packages = $data['packages'];
        unset($data['units'], $data['packages']);

        $product = $this->service->create($data, $units, $packages);

        return back()->with('flash.success', "Produk {$product->name} ({$product->sku}) berhasil dibuat.");
    }

    /**
     * AJAX endpoint untuk memuat detail produk lengkap (supplier + satuan +
     * paket harga) untuk prefill form Edit di halaman /products.
     */
    public function details(Product $product): JsonResponse
    {
        $this->authorize('view', $product);

        $product->load([
            'supplier:id,code,name',
            'category:id,code,name',
            'baseUnit:id,product_id,name,qty_to_base',
            'units.unit:id,name',
            'pricePackages.items.productUnit:id,name,qty_to_base',
        ]);

        return response()->json([
            'product' => $product,
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $data = $request->validated();
        $units = $data['units'] ?? null;
        $packages = $data['packages'] ?? null;
        unset($data['units'], $data['packages']);

        $this->service->update($product, $data, $units, $packages);

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

    /**
     * Hapus banyak produk sekaligus (soft delete). Dipakai fitur pilih-banyak
     * di halaman daftar produk.
     */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:products,id'],
        ]);

        $products = Product::query()->whereIn('id', $data['ids'])->get();

        $deleted = 0;
        foreach ($products as $product) {
            if ($request->user()?->can('delete', $product)) {
                $product->delete();
                $deleted++;
            }
        }

        $skipped = $products->count() - $deleted;
        $message = "{$deleted} produk dihapus.";
        if ($skipped > 0) {
            $message .= " {$skipped} produk dilewati (tidak punya izin).";
        }

        return redirect()
            ->route('products.index')
            ->with($deleted > 0 ? 'flash.success' : 'flash.error', $message);
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
