<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\SalesOrder\ApproveOverrideRequest;
use App\Http\Requests\SalesOrder\CancelSoRequest;
use App\Http\Requests\SalesOrder\RejectSoRequest;
use App\Http\Requests\SalesOrder\StoreSoRequest;
use App\Http\Requests\SalesOrder\UpdateSoRequest;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\Sales\SalesOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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

    public function create(Request $request): InertiaResponse
    {
        $this->authorize('create', SalesOrder::class);

        return Inertia::render('SalesOrders/Create', [
            'customers' => Customer::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->limit(500)
                ->get(['id', 'code', 'name', 'payment_term_days', 'credit_limit', 'tags']),
            'salesUsers' => User::query()
                ->whereHas('role', fn ($q) => $q->where('code', 'sales'))
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            // Prefill customer dari "Sesuaikan → Tambah barang" di halaman SO.
            'prefillCustomerId' => $request->filled('customer_id') ? (int) $request->input('customer_id') : null,
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
            'customer:id,code,name,phone,email,address,credit_limit,payment_term_days',
            'sales:id,name',
            'items',
            'approver:id,name',
            'rejecter:id,name',
            'canceller:id,name',
            'creditOverrideApprover:id,name',
            'reservations.batch:id,batch_code,expired_date',
            'deliveryOrders:id,sales_order_id,do_number,status,do_date',
            'invoices:id,sales_order_id,invoice_number,status,total,outstanding,invoice_date',
        ]);

        // "Sesuaikan": tersedia begitu barang mulai/telah keluar atau faktur
        // terbit — koreksi lewat retur/credit note atau SO baru (bukan edit).
        $canAdjust = in_array($salesOrder->status, [
            SalesOrder::STATUS_PARTIALLY_DELIVERED,
            SalesOrder::STATUS_DELIVERED,
        ], true) || $salesOrder->invoices->isNotEmpty();

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
            'canAdjust' => $canAdjust,
        ]);
    }

    public function edit(SalesOrder $salesOrder): InertiaResponse
    {
        $this->authorize('update', $salesOrder);
        abort_unless($salesOrder->canBeEdited(), 422, 'SO tidak bisa diedit.');

        $salesOrder->load(['customer:id,code,name,payment_term_days,credit_limit,tags', 'items']);

        return Inertia::render('SalesOrders/Edit', [
            'salesOrder' => $salesOrder,
            'customers' => Customer::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->limit(500)
                ->get(['id', 'code', 'name', 'payment_term_days', 'credit_limit', 'tags']),
        ]);
    }

    public function update(UpdateSoRequest $request, SalesOrder $salesOrder): RedirectResponse
    {
        $data = $request->validated();
        $items = $data['items'];
        unset($data['items']);

        $wasApproved = $salesOrder->status === SalesOrder::STATUS_APPROVED;

        try {
            $this->service->updateDraft($salesOrder, $data, $items, $request->user());
        } catch (ValidationException $e) {
            // Tampilkan sbg toast error (mis. stok tak cukup saat re-sync), bukan
            // gagal diam-diam.
            return back()->with('flash.error', $this->firstError($e));
        }

        return redirect()
            ->route('sales-orders.show', $salesOrder)
            ->with('flash.success', $wasApproved
                ? 'SO diperbarui — stok disesuaikan ulang.'
                : 'SO diperbarui.');
    }

    public function submit(Request $request, SalesOrder $salesOrder): RedirectResponse
    {
        $this->authorize('submit', $salesOrder);

        try {
            $this->service->submit($salesOrder, $request->user());
        } catch (ValidationException $e) {
            return back()->with('flash.error', $this->firstError($e));
        }

        $salesOrder->refresh();

        $msg = $salesOrder->status === SalesOrder::STATUS_PENDING_CREDIT_REVIEW
            ? "SO {$salesOrder->so_number} dikirim ke superadmin (over credit limit)."
            : "SO {$salesOrder->so_number} disubmit untuk approval.";

        return back()->with('flash.success', $msg);
    }

    public function approve(Request $request, SalesOrder $salesOrder): RedirectResponse
    {
        $this->authorize('approve', $salesOrder);

        try {
            $this->service->approve($salesOrder, $request->user());
        } catch (ValidationException $e) {
            return back()->with('flash.error', $this->firstError($e));
        }

        return back()->with('flash.success', "SO {$salesOrder->so_number} disetujui & stok ter-reserve.");
    }

    public function approveOverride(ApproveOverrideRequest $request, SalesOrder $salesOrder): RedirectResponse
    {
        // Policy::approveOverride() = false untuk semua kecuali superadmin
        // (lewat before()).
        $this->authorize('approveOverride', $salesOrder);

        try {
            $this->service->approveOverride($salesOrder, $request->validated('reason'), $request->user());
        } catch (ValidationException $e) {
            return back()->with('flash.error', $this->firstError($e));
        }

        return back()->with('flash.success', "SO {$salesOrder->so_number} di-approve dengan credit override.");
    }

    /**
     * Ambil pesan error pertama dari ValidationException — untuk ditampilkan
     * sebagai flash toast (cek stok/credit di service dilempar sbg validation,
     * tapi halaman detail SO tak punya field form untuk menampungnya).
     */
    private function firstError(ValidationException $e): string
    {
        foreach ($e->errors() as $messages) {
            if (! empty($messages)) {
                return (string) $messages[0];
            }
        }

        return 'Aksi gagal — periksa kembali data SO.';
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
     * AJAX: katalog produk untuk SO. Tiap produk punya opsi per satuan
     * (harga dari paket harga default produk). 1 produk = 1 supplier, jadi
     * supplier ikut otomatis dari produk (bukan dipilih sales).
     *
     * Struktur options dijaga kompatibel dgn SoForm.vue: tetap menyertakan
     * supplier_id/supplier_name (nilai supplier bawaan produk). Harga jual
     * final saat SO disimpan diresolve ulang di server berdasar paket harga
     * yang di-assign ke sales lewat Product Group.
     */
    public function productPrices(Request $request): JsonResponse
    {
        $this->authorize('create', SalesOrder::class);

        // Harga jual diselesaikan dengan resolver yang SAMA seperti saat SO
        // disimpan (paket per-customer → sales-group → paket pertama), agar
        // preview di form konsisten dengan harga tersimpan.
        $customerId = $request->filled('customer_id') ? (int) $request->input('customer_id') : null;
        $salesId = $request->filled('sales_id') ? (int) $request->input('sales_id') : null;

        $products = Product::query()
            ->where('is_active', true)
            ->with([
                'supplier:id,code,name',
                'units:id,product_id,name,qty_to_base',
                'pricePackages' => fn ($q) => $q->orderBy('sort_order')->with('items:id,price_package_id,product_unit_id,cost_price,sell_price'),
            ])
            ->orderBy('name')
            ->get(['id', 'sku', 'name', 'supplier_id']);

        $catalog = $products->map(function (Product $product) use ($customerId, $salesId) {
            // Cost per satuan diambil dari paket pertama (referensi margin);
            // sell price diselesaikan per-customer lewat service.
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
                'options' => $product->units->map(fn ($u) => [
                    'supplier_id' => $product->supplier_id,
                    'supplier_name' => $product->supplier?->name,
                    'product_unit_id' => $u->id,
                    'unit_name' => $u->name,
                    'qty_to_base' => (int) $u->qty_to_base,
                    'cost_price' => $costByUnit[$u->id] ?? 0.0,
                    'sell_price' => $this->service->resolveSellPrice($product, $u->id, $salesId, $customerId),
                ])->values(),
            ];
        })->values();

        return response()->json([
            'products' => $catalog,
        ]);
    }
}
