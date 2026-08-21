<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Opening\CancelOpeningRequest;
use App\Http\Requests\Opening\StoreOpeningRequest;
use App\Http\Requests\Opening\UpdateOpeningRequest;
use App\Models\Product;
use App\Models\StockOpening;
use App\Services\Inventory\StockOpeningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class OpeningController extends Controller
{
    public function __construct(private readonly StockOpeningService $service) {}

    public function index(Request $request): InertiaResponse
    {
        $this->authorize('viewAny', StockOpening::class);

        $query = StockOpening::query()
            ->withCount('items')
            ->with('creator:id,name')
            ->orderByDesc('opening_date')
            ->orderByDesc('id');

        if ($search = trim((string) $request->input('q'))) {
            $query->where('opening_number', 'like', "%{$search}%");
        }
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $totals = StockOpening::query()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS draft', [StockOpening::STATUS_DRAFT])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS posted', [StockOpening::STATUS_POSTED])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS cancelled', [StockOpening::STATUS_CANCELLED])
            ->first();

        return Inertia::render('Inventory/Openings/Index', [
            'openings' => $query->paginate(25)->withQueryString(),
            'filters' => [
                'q' => $request->input('q'),
                'status' => $request->input('status'),
            ],
            'stats' => [
                'total' => (int) ($totals->total ?? 0),
                'draft' => (int) ($totals->draft ?? 0),
                'posted' => (int) ($totals->posted ?? 0),
                'cancelled' => (int) ($totals->cancelled ?? 0),
            ],
        ]);
    }

    public function create(): InertiaResponse
    {
        $this->authorize('create', StockOpening::class);

        return Inertia::render('Inventory/Openings/Create');
    }

    public function store(StoreOpeningRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $items = $data['items'];
        unset($data['items']);

        $opening = $this->service->createDraft($data, $items, $request->user());

        return redirect()
            ->route('openings.show', $opening)
            ->with('flash.success', "Stok Awal {$opening->opening_number} dibuat (draft).");
    }

    public function show(StockOpening $opening): InertiaResponse
    {
        $this->authorize('view', $opening);

        $opening->load(['items', 'creator:id,name', 'poster:id,name', 'canceller:id,name']);

        return Inertia::render('Inventory/Openings/Show', [
            'opening' => $opening,
            'canEdit' => $opening->canBeEdited() && (request()->user()?->can('update', $opening) ?? false),
            'canPost' => $opening->canBePosted() && (request()->user()?->can('post', $opening) ?? false),
            'canCancel' => $opening->canBeCancelled() && (request()->user()?->can('cancel', $opening) ?? false),
        ]);
    }

    public function edit(StockOpening $opening): InertiaResponse
    {
        $this->authorize('update', $opening);
        abort_unless($opening->canBeEdited(), 422, 'Stok awal tidak bisa diedit.');

        $opening->load('items');

        return Inertia::render('Inventory/Openings/Edit', [
            'opening' => $opening,
        ]);
    }

    public function update(UpdateOpeningRequest $request, StockOpening $opening): RedirectResponse
    {
        $data = $request->validated();
        $items = $data['items'];
        unset($data['items']);

        $this->service->update($opening, $data, $items, $request->user());

        return redirect()
            ->route('openings.show', $opening)
            ->with('flash.success', 'Stok awal draft diperbarui.');
    }

    public function post(Request $request, StockOpening $opening): RedirectResponse
    {
        $this->authorize('post', $opening);

        $this->service->post($opening, $request->user());

        return back()->with('flash.success', "Stok Awal {$opening->opening_number} di-posting. Stok ter-update.");
    }

    public function cancel(CancelOpeningRequest $request, StockOpening $opening): RedirectResponse
    {
        $this->service->cancel($opening, $request->validated('cancel_reason'), $request->user());

        return back()->with('flash.success', "Stok Awal {$opening->opening_number} dibatalkan.");
    }

    /**
     * AJAX: SEMUA produk aktif yang punya base unit (termasuk yang belum
     * berstok / belum punya batch) — untuk picker di form stok awal.
     */
    public function pickerProducts(Request $request): JsonResponse
    {
        $this->authorize('create', StockOpening::class);

        $products = Product::query()
            ->where('is_active', true)
            ->whereNotNull('base_unit_id')
            ->with([
                'baseUnit:id,product_id,name',
                'units:id,product_id,name,qty_to_base',
            ])
            ->orderBy('name')
            ->limit(2000)
            ->get(['id', 'sku', 'name', 'base_unit_id'])
            ->map(fn (Product $p): array => [
                'product_id' => $p->id,
                'sku' => $p->sku,
                'name' => $p->name,
                'base_unit' => $p->baseUnit?->name,
                'base_unit_id' => $p->base_unit_id,
                'units' => $p->units
                    ->sortByDesc('qty_to_base')
                    ->map(fn ($u) => [
                        'product_unit_id' => $u->id,
                        'name' => $u->name,
                        'qty_to_base' => (int) $u->qty_to_base,
                    ])
                    ->values(),
            ])
            ->values();

        return response()->json(['products' => $products]);
    }
}
