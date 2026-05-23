<?php

namespace App\Http\Controllers\Web;

use App\Exceptions\SupplierHasActiveTransactionsException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Supplier\StoreSupplierRequest;
use App\Http\Requests\Supplier\UpdateSupplierRequest;
use App\Models\Supplier;
use App\Models\SupplierCategory;
use App\Services\Supplier\SupplierService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    public function __construct(private readonly SupplierService $service) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Supplier::class);

        $query = Supplier::query()
            ->with('category:id,code,name')
            ->orderBy('name');

        if ($search = trim((string) $request->input('q'))) {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('npwp', 'like', "%{$search}%");
            });
        }

        if ($categoryCode = $request->input('category')) {
            $query->withCategory($categoryCode);
        }

        if ($request->filled('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        $totals = Supplier::query()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active')
            ->selectRaw('SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) AS inactive')
            ->first();

        return Inertia::render('Suppliers/Index', [
            'suppliers' => $query->paginate(25)->withQueryString(),
            'categories' => SupplierCategory::query()->active()->orderBy('sort_order')->get(['id', 'code', 'name']),
            'filters' => [
                'q' => $request->input('q'),
                'category' => $request->input('category'),
                'active' => $request->input('active'),
            ],
            'stats' => [
                'total' => (int) ($totals->total ?? 0),
                'active' => (int) ($totals->active ?? 0),
                'inactive' => (int) ($totals->inactive ?? 0),
                'categories' => SupplierCategory::query()->count(),
            ],
        ]);
    }

    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        $supplier = $this->service->create($request->validated());

        return redirect()
            ->route('suppliers.show', $supplier)
            ->with('flash.success', "Supplier {$supplier->name} ({$supplier->code}) berhasil dibuat.");
    }

    public function show(Supplier $supplier): Response
    {
        $this->authorize('view', $supplier);

        $supplier->load([
            'category:id,code,name',
            'bankAccounts',
            'documents.uploader:id,name',
        ]);

        return Inertia::render('Suppliers/Show', [
            'supplier' => $supplier,
            'categories' => SupplierCategory::query()->active()->orderBy('sort_order')->get(['id', 'code', 'name']),
            'canUpdate' => request()->user()?->can('update', $supplier) ?? false,
            'canDelete' => request()->user()?->can('delete', $supplier) ?? false,
        ]);
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $this->service->update($supplier, $request->validated());

        return back()->with('flash.success', 'Data supplier diperbarui.');
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        $this->authorize('delete', $supplier);

        try {
            $this->service->delete($supplier);
        } catch (SupplierHasActiveTransactionsException $e) {
            return back()->withErrors(['delete' => $e->reasons]);
        }

        return redirect()
            ->route('suppliers.index')
            ->with('flash.success', "Supplier {$supplier->name} dihapus.");
    }

    public function toggleActive(Supplier $supplier): RedirectResponse
    {
        $this->authorize('toggleActive', $supplier);

        $this->service->toggleActive($supplier);

        return back()->with(
            'flash.success',
            $supplier->is_active ? 'Supplier diaktifkan.' : 'Supplier dinonaktifkan.',
        );
    }

    /**
     * API endpoint untuk autocomplete supplier di dropdown PO/GRN.
     */
    public function apiList(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Supplier::class);

        $query = Supplier::query()->select(['id', 'code', 'name', 'is_active']);

        if ($request->boolean('active', true)) {
            $query->active();
        }

        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        return response()->json([
            'data' => $query->orderBy('name')->limit(50)->get(),
        ]);
    }
}
