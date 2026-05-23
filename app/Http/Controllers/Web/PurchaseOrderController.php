<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\PurchaseOrder\CancelPurchaseOrderRequest;
use App\Http\Requests\PurchaseOrder\ClosePurchaseOrderRequest;
use App\Http\Requests\PurchaseOrder\StorePurchaseOrderRequest;
use App\Http\Requests\PurchaseOrder\UpdatePurchaseOrderRequest;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Services\Purchasing\PurchaseOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class PurchaseOrderController extends Controller
{
    public function __construct(private readonly PurchaseOrderService $service) {}

    public function index(Request $request): InertiaResponse
    {
        $this->authorize('viewAny', PurchaseOrder::class);

        $query = PurchaseOrder::query()
            ->with(['supplier:id,code,name'])
            ->orderByDesc('po_date')
            ->orderByDesc('id');

        if ($search = trim((string) $request->input('q'))) {
            $query->where(function ($q) use ($search): void {
                $q->where('po_number', 'like', "%{$search}%")
                    ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($supplierId = $request->input('supplier_id')) {
            $query->where('supplier_id', $supplierId);
        }

        if ($year = $request->input('year')) {
            $query->where(function ($q) use ($year): void {
                $q->where('fiscal_year', (int) $year)
                    ->orWhere('is_carry_over', true);
            });
        }

        $totals = PurchaseOrder::query()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS draft', [PurchaseOrder::STATUS_DRAFT])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS approved', [PurchaseOrder::STATUS_APPROVED])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS partial', [PurchaseOrder::STATUS_PARTIAL_RECEIVED])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS closed', [PurchaseOrder::STATUS_CLOSED])
            ->first();

        return Inertia::render('PurchaseOrders/Index', [
            'purchaseOrders' => $query->paginate(25)->withQueryString(),
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'filters' => [
                'q' => $request->input('q'),
                'status' => $request->input('status'),
                'supplier_id' => $request->input('supplier_id'),
                'year' => $request->input('year'),
            ],
            'stats' => [
                'total' => (int) ($totals->total ?? 0),
                'draft' => (int) ($totals->draft ?? 0),
                'approved' => (int) ($totals->approved ?? 0),
                'partial' => (int) ($totals->partial ?? 0),
                'closed' => (int) ($totals->closed ?? 0),
            ],
        ]);
    }

    public function create(): InertiaResponse
    {
        $this->authorize('create', PurchaseOrder::class);

        return Inertia::render('PurchaseOrders/Create', [
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name', 'payment_term_days']),
        ]);
    }

    public function store(StorePurchaseOrderRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $items = $data['items'];
        unset($data['items']);

        $po = $this->service->createDraft($data, $items, $request->user());

        return redirect()
            ->route('purchase-orders.show', $po)
            ->with('flash.success', "PO {$po->po_number} dibuat (draft).");
    }

    public function show(PurchaseOrder $purchaseOrder): InertiaResponse
    {
        $this->authorize('view', $purchaseOrder);

        $purchaseOrder->load([
            'supplier:id,code,name,phone,email',
            'items',
            'approver:id,name',
            'closer:id,name',
            'canceller:id,name',
        ]);

        return Inertia::render('PurchaseOrders/Show', [
            'purchaseOrder' => $purchaseOrder,
            'canEdit' => $purchaseOrder->canBeEdited() && (request()->user()?->can('update', $purchaseOrder) ?? false),
            'canApprove' => $purchaseOrder->canBeApproved() && (request()->user()?->can('approve', $purchaseOrder) ?? false),
            'canCancel' => $purchaseOrder->canBeCancelled() && (request()->user()?->can('cancel', $purchaseOrder) ?? false),
            'canClose' => $purchaseOrder->canBeClosed() && (request()->user()?->can('close', $purchaseOrder) ?? false),
        ]);
    }

    public function edit(PurchaseOrder $purchaseOrder): InertiaResponse
    {
        $this->authorize('update', $purchaseOrder);
        abort_unless($purchaseOrder->canBeEdited(), 422, 'PO tidak bisa diedit.');

        $purchaseOrder->load(['supplier', 'items']);

        return Inertia::render('PurchaseOrders/Edit', [
            'purchaseOrder' => $purchaseOrder,
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name', 'payment_term_days']),
        ]);
    }

    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $data = $request->validated();
        $items = $data['items'];
        unset($data['items']);

        $this->service->updateDraft($purchaseOrder, $data, $items, $request->user());

        return redirect()
            ->route('purchase-orders.show', $purchaseOrder)
            ->with('flash.success', 'PO draft diperbarui.');
    }

    public function destroy(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('delete', $purchaseOrder);
        abort_unless($purchaseOrder->canBeEdited(), 422, 'Hanya PO draft yang bisa dihapus.');

        $purchaseOrder->delete();

        return redirect()
            ->route('purchase-orders.index')
            ->with('flash.success', "PO {$purchaseOrder->po_number} dihapus.");
    }

    public function approve(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('approve', $purchaseOrder);

        $this->service->approve($purchaseOrder, $request->user());

        return back()->with('flash.success', "PO {$purchaseOrder->po_number} disetujui & PDF di-generate.");
    }

    public function cancel(CancelPurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->service->cancel($purchaseOrder, $request->validated('cancel_reason'), $request->user());

        return back()->with('flash.success', "PO {$purchaseOrder->po_number} dibatalkan.");
    }

    public function close(ClosePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->service->close($purchaseOrder, $request->validated('close_reason'), $request->user());

        return back()->with('flash.success', "PO {$purchaseOrder->po_number} ditutup.");
    }

    public function downloadPdf(PurchaseOrder $purchaseOrder): Response
    {
        $this->authorize('downloadPdf', $purchaseOrder);

        abort_if(
            $purchaseOrder->pdf_path === null || ! Storage::disk('local')->exists($purchaseOrder->pdf_path),
            404,
            'PDF belum tersedia. Approve PO untuk generate.',
        );

        return response()->file(
            Storage::disk('local')->path($purchaseOrder->pdf_path),
            ['Content-Type' => 'application/pdf'],
        );
    }

    public function regeneratePdf(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('regeneratePdf', $purchaseOrder);

        $this->service->regeneratePdf($purchaseOrder);

        return back()->with('flash.success', 'PDF di-regenerate.');
    }

    /**
     * AJAX endpoint untuk product picker — list produk tertaut ke supplier.
     */
    public function productsForSupplier(Supplier $supplier): JsonResponse
    {
        $this->authorize('create', PurchaseOrder::class);

        $links = SupplierProduct::query()
            ->with(['product:id,sku,name,base_unit_id', 'product.units:id,product_id,level,name,qty_to_base'])
            ->where('supplier_id', $supplier->id)
            ->where('is_active', true)
            ->get();

        $products = $links->map(fn (SupplierProduct $link): array => [
            'product_id' => $link->product->id,
            'sku' => $link->product->sku,
            'name' => $link->product->name,
            'default_cost_price' => (float) $link->default_cost_price,
            'moq' => $link->moq,
            'units' => $link->product->units->map(fn ($u) => [
                'id' => $u->id,
                'level' => $u->level,
                'name' => $u->name,
                'qty_to_base' => $u->qty_to_base,
            ])->values(),
        ])->values();

        return response()->json(['products' => $products]);
    }
}
