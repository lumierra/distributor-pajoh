<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Product Group = bucket produk yang boleh dijual oleh sales tertentu.
 *
 * Skenario:
 *  - Admin bikin group (mis. "Supplier-A Frozen", "Supplier-B Dry") lalu isi produk di dalamnya.
 *  - Sales A di-assign ke beberapa group; tiap assignment punya `monthly_limit` sendiri
 *    sehingga limit per-supplier berbeda dapat dimodelkan dengan multi-group.
 *  - Sales hanya boleh menjual produk yang ada di group yang ter-assign ke dirinya.
 */
class ProductGroupController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ProductGroup::class);

        $query = ProductGroup::query()
            ->withCount(['products', 'salesUsers'])
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($search = trim((string) $request->input('q'))) {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($request->filled('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        return Inertia::render('ProductGroups/Index', [
            'groups' => $query->paginate(20)->withQueryString(),
            'filters' => [
                'q' => $request->input('q'),
                'active' => $request->input('active'),
            ],
        ]);
    }

    public function show(ProductGroup $productGroup): Response
    {
        $this->authorize('view', $productGroup);

        $productGroup->load([
            'products:id,sku,name,brand,is_active',
            'salesUsers:id,name,username,is_active',
        ]);

        $salesRoleId = Role::ofCode(Role::CODE_SALES)->value('id');

        $availableSales = User::query()
            ->where('role_id', $salesRoleId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'username']);

        // Tiap produk dilampirkan list supplier_ids (dari supplier_products)
        // supaya frontend bisa filter "produk dari supplier X".
        $availableProducts = Product::query()
            ->where('is_active', true)
            ->with(['suppliers:id,name,code'])
            ->orderBy('name')
            ->get(['id', 'sku', 'name'])
            ->map(function ($p) {
                return [
                    'id' => $p->id,
                    'sku' => $p->sku,
                    'name' => $p->name,
                    'supplier_ids' => $p->suppliers->pluck('id')->all(),
                ];
            });

        $availableSuppliers = Supplier::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        return Inertia::render('ProductGroups/Show', [
            'group' => $productGroup,
            'availableSales' => $availableSales,
            'availableProducts' => $availableProducts,
            'availableSuppliers' => $availableSuppliers,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', ProductGroup::class);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:32', 'regex:/^[A-Z_][A-Z0-9_]*$/', 'unique:product_groups,code'],
            'name' => ['required', 'string', 'max:64'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        ProductGroup::create($data);

        return back()->with('flash.success', 'Product Group dibuat.');
    }

    public function update(Request $request, ProductGroup $productGroup): RedirectResponse
    {
        $this->authorize('update', $productGroup);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:64'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $productGroup->update($data);

        return back()->with('flash.success', 'Product Group diperbarui.');
    }

    public function destroy(ProductGroup $productGroup): RedirectResponse
    {
        $this->authorize('delete', $productGroup);

        $productGroup->delete();

        return back()->with('flash.success', 'Product Group dihapus.');
    }

    /**
     * Sync daftar produk yang masuk ke group. Diberikan array product_ids penuh.
     */
    public function syncProducts(Request $request, ProductGroup $productGroup): RedirectResponse
    {
        $this->authorize('update', $productGroup);

        $data = $request->validate([
            'product_ids' => ['present', 'array'],
            'product_ids.*' => ['integer', 'exists:products,id'],
        ]);

        $productGroup->products()->sync($data['product_ids'] ?? []);

        return back()->with('flash.success', 'Daftar produk pada group diperbarui.');
    }

    /**
     * Assign / re-assign sales ke group dengan optional monthly_limit per pivot.
     * Input: assignments => [{ user_id, monthly_limit|null }, ...]
     */
    public function syncSales(Request $request, ProductGroup $productGroup): RedirectResponse
    {
        $this->authorize('update', $productGroup);

        $data = $request->validate([
            'assignments' => ['present', 'array'],
            'assignments.*.user_id' => ['required', 'integer', 'exists:users,id'],
            'assignments.*.monthly_limit' => ['nullable', 'numeric', 'min:0'],
        ]);

        $salesRoleId = Role::ofCode(Role::CODE_SALES)->value('id');
        $sync = [];

        foreach ($data['assignments'] as $row) {
            $isSales = User::query()
                ->where('id', $row['user_id'])
                ->where('role_id', $salesRoleId)
                ->exists();
            if (! $isSales) {
                continue;
            }
            $sync[$row['user_id']] = [
                'monthly_limit' => $row['monthly_limit'] ?? null,
                'created_by' => $request->user()?->id,
            ];
        }

        $productGroup->salesUsers()->sync($sync);

        return back()->with('flash.success', 'Assignment sales pada group diperbarui.');
    }
}
