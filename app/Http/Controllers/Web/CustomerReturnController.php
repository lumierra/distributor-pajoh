<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerReturn\CancelCustomerReturnRequest;
use App\Http\Requests\CustomerReturn\SortCustomerReturnRequest;
use App\Http\Requests\CustomerReturn\StoreCustomerReturnRequest;
use App\Http\Requests\CustomerReturn\UpdateCustomerReturnRequest;
use App\Models\Customer;
use App\Models\CustomerReturn;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;
use App\Services\CustomerReturn\CustomerReturnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class CustomerReturnController extends Controller
{
    public function __construct(private readonly CustomerReturnService $service) {}

    public function index(Request $request): InertiaResponse
    {
        $this->authorize('viewAny', CustomerReturn::class);

        $query = CustomerReturn::query()
            ->with(['customer:id,code,name', 'sales:id,name', 'invoice:id,invoice_number', 'creditNote:id,cn_number,remaining_amount,status'])
            ->orderByDesc('return_date')
            ->orderByDesc('id');

        $user = $request->user();
        if ($user && ! $user->isSuperadmin() && $user->hasRole('sales')) {
            $query->where('sales_id', $user->id);
        }

        if ($search = trim((string) $request->input('q'))) {
            $query->where(function ($q) use ($search): void {
                $q->where('return_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$search}%"));
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($brand = $request->input('brand_tag')) {
            $query->where('brand_tag', $brand);
        }

        $totals = CustomerReturn::query()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS draft', [CustomerReturn::STATUS_DRAFT])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS sorted', [CustomerReturn::STATUS_SORTED])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS posted', [CustomerReturn::STATUS_POSTED])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS credited', [CustomerReturn::STATUS_CREDITED])
            ->selectRaw('COALESCE(SUM(CASE WHEN status IN (?,?) THEN total_value ELSE 0 END), 0) AS total_value', [
                CustomerReturn::STATUS_POSTED, CustomerReturn::STATUS_CREDITED,
            ])
            ->first();

        return Inertia::render('CustomerReturns/Index', [
            'customerReturns' => $query->paginate(25)->withQueryString(),
            'filters' => [
                'q' => $request->input('q'),
                'status' => $request->input('status'),
                'brand_tag' => $request->input('brand_tag'),
            ],
            'stats' => [
                'total' => (int) ($totals->total ?? 0),
                'draft' => (int) ($totals->draft ?? 0),
                'sorted' => (int) ($totals->sorted ?? 0),
                'posted' => (int) ($totals->posted ?? 0),
                'credited' => (int) ($totals->credited ?? 0),
                'total_value' => (float) ($totals->total_value ?? 0),
            ],
        ]);
    }

    public function create(Request $request): InertiaResponse
    {
        $this->authorize('create', CustomerReturn::class);

        $invoices = Invoice::query()
            ->openOrPartial()
            ->with('customer:id,code,name')
            ->orderByDesc('invoice_date')
            ->limit(200)
            ->get(['id', 'invoice_number', 'customer_id', 'total', 'outstanding']);

        // Prefill dari halaman SO ("Sesuaikan"): ?invoice_id / ?customer_id.
        $prefillInvoiceId = $request->filled('invoice_id') ? (int) $request->input('invoice_id') : null;
        $prefillCustomerId = $request->filled('customer_id') ? (int) $request->input('customer_id') : null;
        // Kalau invoice_id valid, turunkan customer-nya (biar konsisten).
        if ($prefillInvoiceId !== null) {
            $inv = Invoice::query()->find($prefillInvoiceId);
            if ($inv !== null) {
                $prefillCustomerId = (int) $inv->customer_id;
            } else {
                $prefillInvoiceId = null;
            }
        }

        return Inertia::render('CustomerReturns/Create', [
            'customers' => Customer::query()->where('is_active', true)->orderBy('name')->limit(500)->get(['id', 'code', 'name']),
            'products' => Product::query()->where('is_active', true)->orderBy('name')->limit(500)->get(['id', 'name', 'sku']),
            'openInvoices' => $invoices,
            'salesUsers' => User::query()->whereHas('role', fn ($q) => $q->where('code', 'sales'))->orderBy('name')->get(['id', 'name']),
            'prefill' => [
                'customer_id' => $prefillCustomerId,
                'invoice_id' => $prefillInvoiceId,
            ],
        ]);
    }

    public function store(StoreCustomerReturnRequest $request): RedirectResponse
    {
        $this->authorize('create', CustomerReturn::class);
        $data = $request->validated();

        if ($request->hasFile('proof_photo')) {
            $data['proof_photo_path'] = $this->storeProof($request->file('proof_photo'));
        }

        $cr = $this->service->createDraft($data, $request->user());

        return redirect()
            ->route('customer-returns.show', $cr)
            ->with('flash.success', "Customer return {$cr->return_number} dibuat (draft).");
    }

    public function show(CustomerReturn $customerReturn): InertiaResponse
    {
        $this->authorize('view', $customerReturn);

        $customerReturn->load([
            'customer:id,code,name,phone,address',
            'sales:id,name',
            'invoice:id,invoice_number,total,outstanding',
            'deliveryOrder:id,do_number',
            'items.product:id,name,sku',
            'items.productUnit:id,name,qty_to_base',
            'items.batch:id,batch_code,expired_date',
            'creditNote.applications.invoice:id,invoice_number',
            'sorter:id,name',
            'poster:id,name',
        ]);

        $user = request()->user();

        return Inertia::render('CustomerReturns/Show', [
            'customerReturn' => $customerReturn,
            'canEdit' => $user?->can('update', $customerReturn) ?? false,
            'canSort' => $user?->can('sort', $customerReturn) ?? false,
            'canPost' => $user?->can('post', $customerReturn) ?? false,
            'canCancel' => $user?->can('cancel', $customerReturn) ?? false,
        ]);
    }

    public function edit(CustomerReturn $customerReturn): InertiaResponse
    {
        $this->authorize('update', $customerReturn);
        abort_unless($customerReturn->canBeEdited(), 422, 'CR tidak bisa diedit.');

        $customerReturn->load(['items.product:id,name,sku', 'items.productUnit:id,name']);

        return Inertia::render('CustomerReturns/Edit', [
            'customerReturn' => $customerReturn,
            'products' => Product::query()->where('is_active', true)->orderBy('name')->limit(500)->get(['id', 'name', 'sku']),
        ]);
    }

    public function update(UpdateCustomerReturnRequest $request, CustomerReturn $customerReturn): RedirectResponse
    {
        $this->authorize('update', $customerReturn);

        $this->service->updateDraft($customerReturn, $request->validated(), $request->user());

        return redirect()
            ->route('customer-returns.show', $customerReturn)
            ->with('flash.success', 'CR diperbarui.');
    }

    public function sort(SortCustomerReturnRequest $request, CustomerReturn $customerReturn): RedirectResponse
    {
        $this->authorize('sort', $customerReturn);

        $this->service->updateSortResult($customerReturn, $request->validated('items'), $request->user());

        return back()->with('flash.success', 'Sortir tersimpan. CR siap untuk di-post.');
    }

    public function post(Request $request, CustomerReturn $customerReturn): RedirectResponse
    {
        $this->authorize('post', $customerReturn);

        $this->service->post($customerReturn, $request->user());

        return back()->with('flash.success', "CR {$customerReturn->return_number} di-post. Stok & credit note ter-update.");
    }

    public function cancel(CancelCustomerReturnRequest $request, CustomerReturn $customerReturn): RedirectResponse
    {
        $this->authorize('cancel', $customerReturn);

        $this->service->cancel($customerReturn, $request->validated('cancel_reason'), $request->user());

        return back()->with('flash.success', 'CR dibatalkan.');
    }

    private function storeProof($file): string
    {
        $ext = $file->getClientOriginalExtension() ?: 'jpg';
        $filename = Str::uuid()->toString().'.'.$ext;
        $year = now()->format('Y');
        $path = "customer_returns/{$year}/{$filename}";

        Storage::disk('public')->putFileAs(dirname($path), $file, basename($path));

        return $path;
    }
}
