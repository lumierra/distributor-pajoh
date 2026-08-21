<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Adjustment\CancelAdjustmentRequest;
use App\Http\Requests\Adjustment\StoreAdjustmentRequest;
use App\Http\Requests\Adjustment\UpdateAdjustmentRequest;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Services\Inventory\StockAdjustmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class AdjustmentController extends Controller
{
    public function __construct(private readonly StockAdjustmentService $service) {}

    public function index(Request $request): InertiaResponse
    {
        $this->authorize('viewAny', StockAdjustment::class);

        $query = StockAdjustment::query()
            ->withCount('items')
            ->with('creator:id,name')
            ->orderByDesc('adjustment_date')
            ->orderByDesc('id');

        if ($search = trim((string) $request->input('q'))) {
            $query->where('adjustment_number', 'like', "%{$search}%");
        }
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($reason = $request->input('reason_category')) {
            $query->where('reason_category', $reason);
        }

        $totals = StockAdjustment::query()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS draft', [StockAdjustment::STATUS_DRAFT])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS posted', [StockAdjustment::STATUS_POSTED])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS cancelled', [StockAdjustment::STATUS_CANCELLED])
            ->first();

        return Inertia::render('Inventory/Adjustments/Index', [
            'adjustments' => $query->paginate(25)->withQueryString(),
            'filters' => [
                'q' => $request->input('q'),
                'status' => $request->input('status'),
                'reason_category' => $request->input('reason_category'),
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
        $this->authorize('create', StockAdjustment::class);

        return Inertia::render('Inventory/Adjustments/Create');
    }

    public function store(StoreAdjustmentRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $items = $data['items'];
        unset($data['items']);

        $adjustment = $this->service->createDraft($data, $items, $request->user());

        return redirect()
            ->route('adjustments.show', $adjustment)
            ->with('flash.success', "Adjustment {$adjustment->adjustment_number} dibuat (draft).");
    }

    public function show(StockAdjustment $adjustment): InertiaResponse
    {
        $this->authorize('view', $adjustment);

        $adjustment->load(['items', 'creator:id,name', 'poster:id,name', 'canceller:id,name']);

        return Inertia::render('Inventory/Adjustments/Show', [
            'adjustment' => $adjustment,
            'canEdit' => $adjustment->canBeEdited() && (request()->user()?->can('update', $adjustment) ?? false),
            'canPost' => $adjustment->canBePosted() && (request()->user()?->can('post', $adjustment) ?? false),
            'canCancel' => $adjustment->canBeCancelled() && (request()->user()?->can('cancel', $adjustment) ?? false),
        ]);
    }

    public function edit(StockAdjustment $adjustment): InertiaResponse
    {
        $this->authorize('update', $adjustment);
        abort_unless($adjustment->canBeEdited(), 422, 'Adjustment tidak bisa diedit.');

        $adjustment->load('items');

        return Inertia::render('Inventory/Adjustments/Edit', [
            'adjustment' => $adjustment,
        ]);
    }

    public function update(UpdateAdjustmentRequest $request, StockAdjustment $adjustment): RedirectResponse
    {
        $data = $request->validated();
        $items = $data['items'];
        unset($data['items']);

        $this->service->update($adjustment, $data, $items, $request->user());

        return redirect()
            ->route('adjustments.show', $adjustment)
            ->with('flash.success', 'Adjustment draft diperbarui.');
    }

    public function post(Request $request, StockAdjustment $adjustment): RedirectResponse
    {
        $this->authorize('post', $adjustment);

        $this->service->post($adjustment, $request->user());

        return back()->with('flash.success', "Adjustment {$adjustment->adjustment_number} di-posting. Stok ter-update.");
    }

    public function cancel(CancelAdjustmentRequest $request, StockAdjustment $adjustment): RedirectResponse
    {
        $this->service->cancel($adjustment, $request->validated('cancel_reason'), $request->user());

        return back()->with('flash.success', "Adjustment {$adjustment->adjustment_number} dibatalkan.");
    }

    /**
     * AJAX: produk yang punya stok (≥1 batch) + batch-nya beserta qty on hand.
     * Untuk picker di form adjustment.
     */
    public function stockProducts(Request $request): JsonResponse
    {
        $this->authorize('create', StockAdjustment::class);

        $products = Product::query()
            ->where('is_active', true)
            ->whereHas('batches')
            ->with([
                'baseUnit:id,product_id,name',
                'units:id,product_id,name,qty_to_base',
                'batches' => fn ($q) => $q->where('is_active', true)
                    ->with('balance:id,product_id,batch_id,qty_on_hand')
                    ->orderBy('batch_code'),
            ])
            ->orderBy('name')
            ->limit(1000)
            ->get(['id', 'sku', 'name', 'base_unit_id'])
            ->map(fn (Product $p): array => [
                'product_id' => $p->id,
                'sku' => $p->sku,
                'name' => $p->name,
                'base_unit' => $p->baseUnit?->name,
                'base_unit_id' => $p->base_unit_id,
                'units' => $p->units->map(fn ($u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'qty_to_base' => (int) $u->qty_to_base,
                ])->values(),
                'batches' => $p->batches->map(fn ($b) => [
                    'batch_id' => $b->id,
                    'batch_code' => $b->batch_code,
                    'qty_on_hand' => (int) ($b->balance?->qty_on_hand ?? 0),
                ])->values(),
            ])
            ->filter(fn ($p) => count($p['batches']) > 0)
            ->values();

        return response()->json(['products' => $products]);
    }
}
