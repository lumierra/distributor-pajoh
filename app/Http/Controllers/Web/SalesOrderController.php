<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\SalesOrder\ApproveOverrideRequest;
use App\Http\Requests\SalesOrder\CancelSoRequest;
use App\Http\Requests\SalesOrder\RejectSoRequest;
use App\Http\Requests\SalesOrder\StoreSoRequest;
use App\Http\Requests\SalesOrder\UpdateSoRequest;
use App\Models\Customer;
use App\Models\PriceTier;
use App\Models\ProductPrice;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\Sales\SalesOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class SalesOrderController extends Controller
{
    public function __construct(private readonly SalesOrderService $service) {}

    public function index(Request $request): InertiaResponse
    {
        $this->authorize('viewAny', SalesOrder::class);

        $query = SalesOrder::query()
            ->with(['customer:id,code,name', 'sales:id,name'])
            ->orderByDesc('so_date')
            ->orderByDesc('id');

        // Sales hanya lihat SO miliknya
        $user = $request->user();
        if ($user && ! $user->isSuperadmin() && $user->hasRole('sales')) {
            $query->where('sales_id', $user->id);
        }

        if ($search = trim((string) $request->input('q'))) {
            $query->where(function ($q) use ($search): void {
                $q->where('so_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$search}%"));
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($customerId = $request->input('customer_id')) {
            $query->where('customer_id', $customerId);
        }

        if ($salesId = $request->input('sales_id')) {
            $query->where('sales_id', $salesId);
        }

        if ($year = $request->input('year')) {
            $query->where(function ($q) use ($year): void {
                $q->where('fiscal_year', (int) $year)->orWhere('is_carry_over', true);
            });
        }

        $totals = SalesOrder::query()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS draft', [SalesOrder::STATUS_DRAFT])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS submitted', [SalesOrder::STATUS_SUBMITTED])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS pending_credit', [SalesOrder::STATUS_PENDING_CREDIT_REVIEW])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS approved', [SalesOrder::STATUS_APPROVED])
            ->first();

        return Inertia::render('SalesOrders/Index', [
            'salesOrders' => $query->paginate(25)->withQueryString(),
            'customers' => Customer::query()->where('is_active', true)->orderBy('name')->limit(500)->get(['id', 'code', 'name']),
            'salesUsers' => User::query()
                ->whereHas('role', fn ($q) => $q->where('code', 'sales'))
                ->orderBy('name')
                ->get(['id', 'name']),
            'filters' => [
                'q' => $request->input('q'),
                'status' => $request->input('status'),
                'customer_id' => $request->input('customer_id'),
                'sales_id' => $request->input('sales_id'),
                'year' => $request->input('year'),
            ],
            'stats' => [
                'total' => (int) ($totals->total ?? 0),
                'draft' => (int) ($totals->draft ?? 0),
                'submitted' => (int) ($totals->submitted ?? 0),
                'pending_credit' => (int) ($totals->pending_credit ?? 0),
                'approved' => (int) ($totals->approved ?? 0),
            ],
        ]);
    }

    public function create(): InertiaResponse
    {
        $this->authorize('create', SalesOrder::class);

        return Inertia::render('SalesOrders/Create', [
            'customers' => Customer::query()
                ->where('is_active', true)
                ->with('priceTier:id,code,name')
                ->orderBy('name')
                ->limit(500)
                ->get(['id', 'code', 'name', 'price_tier_id', 'payment_term_days', 'credit_limit', 'tags']),
            'salesUsers' => User::query()
                ->whereHas('role', fn ($q) => $q->where('code', 'sales'))
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function store(StoreSoRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $items = $data['items'];
        unset($data['items']);

        $so = $this->service->createDraft($data, $items, $request->user());

        return redirect()
            ->route('sales-orders.show', $so)
            ->with('flash.success', "SO {$so->so_number} dibuat (draft).");
    }

    public function show(SalesOrder $salesOrder): InertiaResponse
    {
        $this->authorize('view', $salesOrder);

        $salesOrder->load([
            'customer:id,code,name,phone,email,address,credit_limit,payment_term_days,price_tier_id',
            'customer.priceTier:id,code,name',
            'sales:id,name',
            'items',
            'approver:id,name',
            'rejecter:id,name',
            'canceller:id,name',
            'creditOverrideApprover:id,name',
            'reservations.batch:id,batch_code,expired_date',
        ]);

        return Inertia::render('SalesOrders/Show', [
            'salesOrder' => $salesOrder,
            'canEdit' => $salesOrder->canBeEdited() && (request()->user()?->can('update', $salesOrder) ?? false),
            'canSubmit' => $salesOrder->canBeSubmitted() && (request()->user()?->can('submit', $salesOrder) ?? false),
            'canApprove' => $salesOrder->status === SalesOrder::STATUS_SUBMITTED
                && (request()->user()?->can('approve', $salesOrder) ?? false),
            'canApproveOverride' => $salesOrder->status === SalesOrder::STATUS_PENDING_CREDIT_REVIEW
                && (request()->user()?->isSuperadmin() ?? false),
            'canReject' => $salesOrder->canBeRejected() && (request()->user()?->can('reject', $salesOrder) ?? false),
            'canCancel' => $salesOrder->canBeCancelled() && (request()->user()?->can('cancel', $salesOrder) ?? false),
        ]);
    }

    public function edit(SalesOrder $salesOrder): InertiaResponse
    {
        $this->authorize('update', $salesOrder);
        abort_unless($salesOrder->canBeEdited(), 422, 'SO tidak bisa diedit.');

        $salesOrder->load(['customer:id,code,name,price_tier_id,payment_term_days,credit_limit,tags', 'items']);

        return Inertia::render('SalesOrders/Edit', [
            'salesOrder' => $salesOrder,
            'customers' => Customer::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->limit(500)
                ->get(['id', 'code', 'name', 'price_tier_id', 'payment_term_days', 'credit_limit', 'tags']),
        ]);
    }

    public function update(UpdateSoRequest $request, SalesOrder $salesOrder): RedirectResponse
    {
        $data = $request->validated();
        $items = $data['items'];
        unset($data['items']);

        $this->service->updateDraft($salesOrder, $data, $items, $request->user());

        return redirect()
            ->route('sales-orders.show', $salesOrder)
            ->with('flash.success', 'SO draft diperbarui.');
    }

    public function submit(Request $request, SalesOrder $salesOrder): RedirectResponse
    {
        $this->authorize('submit', $salesOrder);

        $this->service->submit($salesOrder, $request->user());
        $salesOrder->refresh();

        $msg = $salesOrder->status === SalesOrder::STATUS_PENDING_CREDIT_REVIEW
            ? "SO {$salesOrder->so_number} dikirim ke superadmin (over credit limit)."
            : "SO {$salesOrder->so_number} disubmit untuk approval.";

        return back()->with('flash.success', $msg);
    }

    public function approve(Request $request, SalesOrder $salesOrder): RedirectResponse
    {
        $this->authorize('approve', $salesOrder);

        $this->service->approve($salesOrder, $request->user());

        return back()->with('flash.success', "SO {$salesOrder->so_number} disetujui & stok ter-reserve.");
    }

    public function approveOverride(ApproveOverrideRequest $request, SalesOrder $salesOrder): RedirectResponse
    {
        // Policy::approveOverride() = false untuk semua kecuali superadmin
        // (lewat before()).
        $this->authorize('approveOverride', $salesOrder);

        $this->service->approveOverride($salesOrder, $request->validated('reason'), $request->user());

        return back()->with('flash.success', "SO {$salesOrder->so_number} di-approve dengan credit override.");
    }

    public function reject(RejectSoRequest $request, SalesOrder $salesOrder): RedirectResponse
    {
        $this->service->reject($salesOrder, $request->validated('rejection_reason'), $request->user());

        return back()->with('flash.success', "SO {$salesOrder->so_number} ditolak.");
    }

    public function cancel(CancelSoRequest $request, SalesOrder $salesOrder): RedirectResponse
    {
        $this->service->cancel($salesOrder, $request->validated('cancel_reason'), $request->user());

        return back()->with('flash.success', "SO {$salesOrder->so_number} dibatalkan.");
    }

    /**
     * AJAX: price matrix produk untuk tier customer (saat sales pilih produk).
     */
    public function productPrices(Request $request, Customer $customer): JsonResponse
    {
        $this->authorize('create', SalesOrder::class);

        $tierId = (int) $customer->price_tier_id;

        $prices = ProductPrice::query()
            ->where('price_tier_id', $tierId)
            ->with(['product:id,sku,name,is_active', 'unit:id,product_id,level,name,qty_to_base'])
            ->whereHas('product', fn ($q) => $q->where('is_active', true))
            ->get();

        $grouped = $prices->groupBy('product_id')->map(function ($rows) {
            $first = $rows->first();

            return [
                'product_id' => $first->product->id,
                'sku' => $first->product->sku,
                'name' => $first->product->name,
                'units' => $rows->map(fn ($p) => [
                    'id' => $p->unit->id,
                    'level' => $p->unit->level,
                    'name' => $p->unit->name,
                    'qty_to_base' => (int) $p->unit->qty_to_base,
                    'price' => (float) $p->price,
                ])->values(),
            ];
        })->values();

        return response()->json([
            'tier' => PriceTier::query()->find($tierId)?->only(['id', 'code', 'name']),
            'products' => $grouped,
        ]);
    }
}
