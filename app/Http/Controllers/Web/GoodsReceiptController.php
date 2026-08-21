<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Grn\CancelGrnRequest;
use App\Http\Requests\Grn\PostGrnRequest;
use App\Http\Requests\Grn\RejectGrnRequest;
use App\Http\Requests\Grn\StoreGrnRequest;
use App\Http\Requests\Grn\UpdateGrnRequest;
use App\Models\GoodsReceipt;
use App\Models\GrnItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\Purchasing\GoodsReceiptService;
use App\Services\Purchasing\GrnPdfRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class GoodsReceiptController extends Controller
{
    public function __construct(
        private readonly GoodsReceiptService $service,
        private readonly GrnPdfRenderer $pdf,
    ) {}

    public function index(Request $request): InertiaResponse
    {
        $this->authorize('viewAny', GoodsReceipt::class);

        $query = GoodsReceipt::query()
            ->with(['purchaseOrder:id,po_number', 'supplier:id,code,name'])
            ->orderByDesc('received_date')
            ->orderByDesc('id');

        if ($search = trim((string) $request->input('q'))) {
            $query->where(function ($q) use ($search): void {
                $q->where('grn_number', 'like', "%{$search}%")
                    ->orWhereHas('purchaseOrder', fn ($p) => $p->where('po_number', 'like', "%{$search}%"))
                    ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', "%{$search}%"));
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($supplierId = $request->input('supplier_id')) {
            $query->where('supplier_id', $supplierId);
        }

        if ($year = $request->input('year')) {
            $query->where('fiscal_year', (int) $year);
        }

        $totals = GoodsReceipt::query()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS draft', [GoodsReceipt::STATUS_DRAFT])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS submitted', [GoodsReceipt::STATUS_SUBMITTED])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS posted', [GoodsReceipt::STATUS_POSTED])
            ->selectRaw('SUM(CASE WHEN has_discrepancy = 1 AND status = ? THEN 1 ELSE 0 END) AS discrepancy', [GoodsReceipt::STATUS_POSTED])
            ->first();

        return Inertia::render('GoodsReceipts/Index', [
            'grns' => $query->paginate(25)->withQueryString(),
            'filters' => [
                'q' => $request->input('q'),
                'status' => $request->input('status'),
                'supplier_id' => $request->input('supplier_id'),
                'year' => $request->input('year'),
            ],
            'stats' => [
                'total' => (int) ($totals->total ?? 0),
                'draft' => (int) ($totals->draft ?? 0),
                'submitted' => (int) ($totals->submitted ?? 0),
                'posted' => (int) ($totals->posted ?? 0),
                'discrepancy' => (int) ($totals->discrepancy ?? 0),
            ],
        ]);
    }

    public function create(Request $request): InertiaResponse
    {
        $this->authorize('create', GoodsReceipt::class);

        // List PO yang masih open (approved / partial_received)
        $openPos = PurchaseOrder::query()
            ->whereIn('status', [PurchaseOrder::STATUS_APPROVED, PurchaseOrder::STATUS_PARTIAL_RECEIVED])
            ->with('supplier:id,code,name')
            ->orderByDesc('po_date')
            ->limit(100)
            ->get(['id', 'po_number', 'po_date', 'supplier_id', 'status']);

        // Daftar supplier aktif untuk mode penerimaan langsung (tanpa PO).
        $suppliers = Supplier::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->limit(500)
            ->get(['id', 'code', 'name', 'payment_term_days']);

        return Inertia::render('GoodsReceipts/Create', [
            'openPurchaseOrders' => $openPos,
            'suppliers' => $suppliers,
            'selectedPo' => $request->input('purchase_order_id')
                ? $this->loadPoForGrn((int) $request->input('purchase_order_id'))
                : null,
        ]);
    }

    public function store(StoreGrnRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $items = $data['items'];
        unset($data['items']);

        $grn = $this->service->createDraft($data, $items, $request->user());

        return redirect()
            ->route('grns.show', $grn)
            ->with('flash.success', "GRN {$grn->grn_number} dibuat (draft).");
    }

    public function show(GoodsReceipt $goodsReceipt): InertiaResponse
    {
        $this->authorize('view', $goodsReceipt);

        $goodsReceipt->load([
            'purchaseOrder:id,po_number,po_date,supplier_id',
            'purchaseOrder.supplier:id,code,name',
            'supplier:id,code,name',
            'items.poItem:id,qty_ordered,qty_received,bonus_qty,bonus_qty_received,unit_net_cost',
            'items.batch:id,batch_code,production_date,expired_date',
            'items.pendingSettler:id,name',
            'attachments' => fn ($q) => $q->with('uploader:id,name'),
            'receiver:id,name',
            'submitter:id,name',
            'poster:id,name',
            'rejecter:id,name',
        ]);

        // Settle pending per ITEM (bukan per GRN) — susulan datang per produk.
        $canManagePending = request()->user()?->can('settleDirectPending', $goodsReceipt) ?? false;

        return Inertia::render('GoodsReceipts/Show', [
            'grn' => $goodsReceipt,
            'canEdit' => $goodsReceipt->canBeEdited() && (request()->user()?->can('update', $goodsReceipt) ?? false),
            'canSubmit' => $goodsReceipt->canBeSubmitted() && (request()->user()?->can('submit', $goodsReceipt) ?? false),
            'canCancel' => $goodsReceipt->canBeCancelled() && (request()->user()?->can('cancel', $goodsReceipt) ?? false),
            'canPost' => $goodsReceipt->canBePosted() && (request()->user()?->can('post', $goodsReceipt) ?? false),
            'canReject' => $goodsReceipt->canBeRejected() && (request()->user()?->can('reject', $goodsReceipt) ?? false),
            'canManageAttachments' => request()->user()?->can('manageAttachments', $goodsReceipt) ?? false,
            'hasOverReceive' => $goodsReceipt->hasOverReceive(),
            // Boleh kelola pending per item (tombol muncul per baris di frontend).
            'canManagePending' => $canManagePending,
        ]);
    }

    public function edit(GoodsReceipt $goodsReceipt): InertiaResponse
    {
        $this->authorize('update', $goodsReceipt);
        abort_unless($goodsReceipt->canBeEdited(), 422, 'GRN tidak bisa diedit.');

        $goodsReceipt->load(['purchaseOrder', 'supplier:id,code,name', 'items.poItem']);

        return Inertia::render('GoodsReceipts/Edit', [
            'grn' => $goodsReceipt,
            'po' => $goodsReceipt->purchase_order_id
                ? $this->loadPoForGrn($goodsReceipt->purchase_order_id)
                : null,
        ]);
    }

    public function update(UpdateGrnRequest $request, GoodsReceipt $goodsReceipt): RedirectResponse
    {
        $data = $request->validated();
        $items = $data['items'];
        unset($data['items']);

        $this->service->updateDraft($goodsReceipt, $data, $items, $request->user());

        return redirect()
            ->route('grns.show', $goodsReceipt)
            ->with('flash.success', 'GRN draft diperbarui.');
    }

    public function destroy(GoodsReceipt $goodsReceipt): RedirectResponse
    {
        $this->authorize('delete', $goodsReceipt);

        $goodsReceipt->delete();

        return redirect()
            ->route('grns.index')
            ->with('flash.success', "GRN {$goodsReceipt->grn_number} dihapus.");
    }

    public function submit(Request $request, GoodsReceipt $goodsReceipt): RedirectResponse
    {
        $this->authorize('submit', $goodsReceipt);

        $this->service->submit($goodsReceipt, $request->user());

        return back()->with('flash.success', "GRN {$goodsReceipt->grn_number} disubmit untuk review.");
    }

    public function cancel(CancelGrnRequest $request, GoodsReceipt $goodsReceipt): RedirectResponse
    {
        $this->service->cancel($goodsReceipt, $request->validated('cancel_reason'), $request->user());

        return back()->with('flash.success', "GRN {$goodsReceipt->grn_number} dibatalkan.");
    }

    public function reject(RejectGrnRequest $request, GoodsReceipt $goodsReceipt): RedirectResponse
    {
        $this->service->reject($goodsReceipt, $request->validated('rejection_reason'), $request->user());

        return back()->with('flash.success', "GRN {$goodsReceipt->grn_number} ditolak. Operator akan diberitahu.");
    }

    public function settleItemPending(Request $request, GrnItem $grnItem): RedirectResponse
    {
        $this->authorize('settleDirectPending', $grnItem->goodsReceipt);

        $this->service->settleItemPending($grnItem, $request->user());

        return back()->with('flash.success', 'Pending item ditandai selesai.');
    }

    public function unsettleItemPending(Request $request, GrnItem $grnItem): RedirectResponse
    {
        $this->authorize('settleDirectPending', $grnItem->goodsReceipt);

        $this->service->unsettleItemPending($grnItem, $request->user());

        return back()->with('flash.success', 'Penandaan pending item dibatalkan.');
    }

    public function post(PostGrnRequest $request, GoodsReceipt $goodsReceipt): RedirectResponse
    {
        $approveOverReceive = (bool) $request->validated('approve_over_receive', false);

        $this->service->post($goodsReceipt, $request->user(), $approveOverReceive);
        $this->pdf->generate($goodsReceipt->refresh());

        return back()->with('flash.success', "GRN {$goodsReceipt->grn_number} di-posting. Stok ter-update.");
    }

    public function downloadPdf(GoodsReceipt $goodsReceipt): BinaryFileResponse
    {
        $this->authorize('downloadPdf', $goodsReceipt);

        abort_if(
            $goodsReceipt->pdf_path === null || ! Storage::disk('local')->exists($goodsReceipt->pdf_path),
            404,
            'PDF belum tersedia. Posting GRN untuk generate.',
        );

        return response()->file(
            Storage::disk('local')->path($goodsReceipt->pdf_path),
            ['Content-Type' => 'application/pdf'],
        );
    }

    /**
     * AJAX: detail PO + items untuk operator pilih + isi qty.
     */
    public function poDetails(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorize('create', GoodsReceipt::class);

        return response()->json([
            'po' => $this->loadPoForGrn($purchaseOrder->id),
        ]);
    }

    /**
     * AJAX: produk milik supplier untuk mode penerimaan langsung (tanpa PO).
     * cost_price per satuan = harga modal dari paket harga pertama produk
     * (sekadar acuan; HPP aktual dari input operator).
     */
    public function supplierProducts(Supplier $supplier): JsonResponse
    {
        $this->authorize('create', GoodsReceipt::class);

        $products = Product::query()
            ->where('supplier_id', $supplier->id)
            ->where('is_active', true)
            ->with([
                'units:id,product_id,name,qty_to_base',
                'pricePackages' => fn ($q) => $q->orderBy('sort_order')
                    ->with('items:id,price_package_id,product_unit_id,cost_price'),
            ])
            ->orderBy('name')
            ->get(['id', 'sku', 'name'])
            ->map(function (Product $product): array {
                $defaultPackage = $product->pricePackages->first();
                $costByUnit = [];
                if ($defaultPackage) {
                    foreach ($defaultPackage->items as $item) {
                        $costByUnit[$item->product_unit_id] = (float) $item->cost_price;
                    }
                }

                return [
                    'product_id' => $product->id,
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'units' => $product->units->map(fn ($u) => [
                        'id' => $u->id,
                        'name' => $u->name,
                        'qty_to_base' => (int) $u->qty_to_base,
                        'cost_price' => $costByUnit[$u->id] ?? 0.0,
                    ])->values(),
                ];
            })->values();

        return response()->json(['products' => $products]);
    }

    private function loadPoForGrn(int $poId): ?array
    {
        $po = PurchaseOrder::query()
            ->with([
                'supplier:id,code,name,address',
                'items:id,purchase_order_id,product_id,product_unit_id,product_name_snapshot,product_sku_snapshot,product_unit_name_snapshot,qty_ordered,qty_received,bonus_qty,bonus_qty_received,unit_net_cost',
            ])
            ->find($poId);

        if ($po === null) {
            return null;
        }

        return [
            'id' => $po->id,
            'po_number' => $po->po_number,
            'po_date' => $po->po_date?->toDateString(),
            'status' => $po->status,
            'supplier' => $po->supplier,
            'items' => $po->items->map(fn ($i) => [
                'id' => $i->id,
                'product_id' => $i->product_id,
                'product_unit_id' => $i->product_unit_id,
                'product_name' => $i->product_name_snapshot,
                'product_sku' => $i->product_sku_snapshot,
                'unit_name' => $i->product_unit_name_snapshot,
                'qty_ordered' => (int) $i->qty_ordered,
                'qty_received' => (int) $i->qty_received,
                'qty_remaining' => max(0, (int) $i->qty_ordered - (int) $i->qty_received),
                'bonus_qty' => (int) $i->bonus_qty,
                'bonus_qty_received' => (int) $i->bonus_qty_received,
                'unit_net_cost' => (float) $i->unit_net_cost,
            ])->values(),
        ];
    }
}
