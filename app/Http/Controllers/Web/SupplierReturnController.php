<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\SupplierReturn\CancelSupplierReturnRequest;
use App\Http\Requests\SupplierReturn\MarkSentSupplierReturnRequest;
use App\Http\Requests\SupplierReturn\SettleSupplierReturnRequest;
use App\Http\Requests\SupplierReturn\StoreSupplierReturnRequest;
use App\Http\Requests\SupplierReturn\UpdateSupplierReturnRequest;
use App\Models\Supplier;
use App\Models\SupplierReturn;
use App\Services\SupplierReturn\SupplierReturnService;
use App\Services\SupplierReturn\SupplierReturnSourceResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class SupplierReturnController extends Controller
{
    public function __construct(
        private readonly SupplierReturnService $service,
        private readonly SupplierReturnSourceResolver $resolver,
    ) {}

    public function index(Request $request): InertiaResponse
    {
        $this->authorize('viewAny', SupplierReturn::class);

        $query = SupplierReturn::query()
            ->with(['supplier:id,code,name', 'approver:id,name', 'sender:id,name'])
            ->orderByDesc('return_date')
            ->orderByDesc('id');

        if ($search = trim((string) $request->input('q'))) {
            $query->where(function ($q) use ($search): void {
                $q->where('return_number', 'like', "%{$search}%")
                    ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', "%{$search}%"));
            });
        }
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($supplierId = $request->input('supplier_id')) {
            $query->where('supplier_id', $supplierId);
        }

        $totals = SupplierReturn::query()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS draft', [SupplierReturn::STATUS_DRAFT])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS approved', [SupplierReturn::STATUS_APPROVED])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS sent', [SupplierReturn::STATUS_SENT])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS settled', [SupplierReturn::STATUS_SETTLED])
            ->selectRaw('COALESCE(SUM(claim_amount), 0) AS total_claim')
            ->first();

        return Inertia::render('SupplierReturns/Index', [
            'supplierReturns' => $query->paginate(25)->withQueryString(),
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'filters' => [
                'q' => $request->input('q'),
                'status' => $request->input('status'),
                'supplier_id' => $request->input('supplier_id'),
            ],
            'stats' => [
                'total' => (int) ($totals->total ?? 0),
                'draft' => (int) ($totals->draft ?? 0),
                'approved' => (int) ($totals->approved ?? 0),
                'sent' => (int) ($totals->sent ?? 0),
                'settled' => (int) ($totals->settled ?? 0),
                'total_claim' => (float) ($totals->total_claim ?? 0),
            ],
        ]);
    }

    public function create(Request $request): InertiaResponse
    {
        $this->authorize('create', SupplierReturn::class);

        $supplierId = $request->input('supplier_id');
        $sources = ['grn_damaged' => [], 'customer_return_bs' => [], 'stock' => []];

        if ($supplierId) {
            $supplier = Supplier::query()->find((int) $supplierId);
            if ($supplier !== null) {
                $sources = [
                    'grn_damaged' => $this->resolver->fromGrnDamaged($supplier)->values(),
                    'customer_return_bs' => $this->resolver->fromCustomerReturnBs($supplier)->values(),
                    'stock' => $this->resolver->fromStock($supplier)->values(),
                ];
            }
        }

        return Inertia::render('SupplierReturns/Create', [
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'selectedSupplierId' => $supplierId,
            'sources' => $sources,
        ]);
    }

    public function store(StoreSupplierReturnRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('proof_photo')) {
            $data['proof_photo_path'] = $this->storeProof($request->file('proof_photo'));
        }

        $sr = $this->service->createDraft($data, $request->user());

        return redirect()
            ->route('supplier-returns.show', $sr)
            ->with('flash.success', "Retur supplier {$sr->return_number} dibuat.");
    }

    public function show(SupplierReturn $supplierReturn): InertiaResponse
    {
        $this->authorize('view', $supplierReturn);

        $supplierReturn->load([
            'supplier:id,code,name,phone,address',
            'items.product:id,name,sku',
            'items.productUnit:id,name,qty_to_base',
            'items.batch:id,batch_code,expired_date',
            'items.grnItem:id,goods_receipt_id',
            'items.grnItem.goodsReceipt:id,grn_number',
            'items.customerReturnItem:id,customer_return_id',
            'items.customerReturnItem.customerReturn:id,return_number',
            'approver:id,name',
            'sender:id,name',
            'settler:id,name',
        ]);

        $user = request()->user();

        return Inertia::render('SupplierReturns/Show', [
            'supplierReturn' => $supplierReturn,
            'canEdit' => $user?->can('update', $supplierReturn) ?? false,
            'canApprove' => $supplierReturn->canBeApproved() && ($user?->isSuperadmin() ?? false),
            'canMarkSent' => $user?->can('markSent', $supplierReturn) ?? false,
            'canSettle' => $user?->can('settle', $supplierReturn) ?? false,
            'canCancel' => $user?->can('cancel', $supplierReturn) ?? false,
        ]);
    }

    public function edit(SupplierReturn $supplierReturn): InertiaResponse
    {
        $this->authorize('update', $supplierReturn);
        abort_unless($supplierReturn->canBeEdited(), 422, 'SR tidak bisa diedit.');

        $supplierReturn->load(['items.product:id,name,sku', 'items.productUnit:id,name']);

        return Inertia::render('SupplierReturns/Edit', [
            'supplierReturn' => $supplierReturn,
        ]);
    }

    public function update(UpdateSupplierReturnRequest $request, SupplierReturn $supplierReturn): RedirectResponse
    {
        $this->authorize('update', $supplierReturn);

        $this->service->updateDraft($supplierReturn, $request->validated(), $request->user());

        return redirect()
            ->route('supplier-returns.show', $supplierReturn)
            ->with('flash.success', 'SR diperbarui.');
    }

    public function approve(Request $request, SupplierReturn $supplierReturn): RedirectResponse
    {
        abort_unless($request->user()?->isSuperadmin(), 403, 'Hanya superadmin yang bisa approve.');

        $this->service->approve($supplierReturn, $request->user());

        return back()->with('flash.success', "SR {$supplierReturn->return_number} di-approve.");
    }

    public function markSent(MarkSentSupplierReturnRequest $request, SupplierReturn $supplierReturn): RedirectResponse
    {
        $this->authorize('markSent', $supplierReturn);

        $this->service->markSent(
            $supplierReturn,
            $request->validated('sent_date'),
            $request->user(),
        );

        return back()->with('flash.success', 'SR di-mark sent. Stok diperbarui untuk source stock.');
    }

    public function settle(SettleSupplierReturnRequest $request, SupplierReturn $supplierReturn): RedirectResponse
    {
        $this->authorize('settle', $supplierReturn);

        $this->service->settle(
            $supplierReturn,
            (float) $request->validated('settled_amount'),
            $request->validated('settlement_notes'),
            $request->user(),
        );

        return back()->with('flash.success', 'SR settled.');
    }

    public function cancel(CancelSupplierReturnRequest $request, SupplierReturn $supplierReturn): RedirectResponse
    {
        $this->authorize('cancel', $supplierReturn);

        $this->service->cancel($supplierReturn, $request->validated('cancel_reason'), $request->user());

        return back()->with('flash.success', 'SR dibatalkan.');
    }

    private function storeProof($file): string
    {
        $ext = $file->getClientOriginalExtension() ?: 'jpg';
        $filename = Str::uuid()->toString().'.'.$ext;
        $year = now()->format('Y');
        $path = "supplier_returns/{$year}/{$filename}";

        Storage::disk('public')->putFileAs(dirname($path), $file, basename($path));

        return $path;
    }
}
