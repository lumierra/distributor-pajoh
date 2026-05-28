<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\ReassignSalesRequest;
use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Models\Customer;
use App\Models\CustomerType;
use App\Models\Invoice;
use App\Models\PriceTier;
use App\Models\User;
use App\Services\Customer\CustomerOutstandingService;
use App\Services\Customer\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function __construct(
        private readonly CustomerService $service,
        private readonly CustomerOutstandingService $outstanding,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Customer::class);

        $query = Customer::query()
            ->with([
                'type:id,code,name',
                'priceTier:id,code,name',
                'assignedSales:id,name',
            ])
            ->orderBy('name');

        if ($search = trim((string) $request->input('q'))) {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('owner_name', 'like', "%{$search}%");
            });
        }

        if ($typeId = $request->input('type_id')) {
            $query->where('customer_type_id', $typeId);
        }

        if ($tierId = $request->input('tier_id')) {
            $query->where('price_tier_id', $tierId);
        }

        if ($request->filled('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        $totals = Customer::query()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active')
            ->selectRaw('SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) AS inactive')
            ->selectRaw('SUM(CASE WHEN latitude IS NULL OR longitude IS NULL THEN 1 ELSE 0 END) AS without_geo')
            ->first();

        return Inertia::render('Customers/Index', [
            'customers' => $query->paginate(25)->withQueryString(),
            'types' => CustomerType::query()->active()->orderBy('sort_order')->get(['id', 'code', 'name']),
            'tiers' => PriceTier::query()->active()->orderBy('sort_order')->get(['id', 'code', 'name']),
            'filters' => [
                'q' => $request->input('q'),
                'type_id' => $request->input('type_id'),
                'tier_id' => $request->input('tier_id'),
                'active' => $request->input('active'),
            ],
            'stats' => [
                'total' => (int) ($totals->total ?? 0),
                'active' => (int) ($totals->active ?? 0),
                'inactive' => (int) ($totals->inactive ?? 0),
                'without_geo' => (int) ($totals->without_geo ?? 0),
            ],
            'hasImportErrors' => session()->has('customer_import.errors'),
        ]);
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $customer = $this->service->create($request->validated());

        return redirect()
            ->route('customers.show', $customer)
            ->with('flash.success', "Customer {$customer->name} ({$customer->code}) berhasil dibuat.");
    }

    public function show(Customer $customer): Response
    {
        $this->authorize('view', $customer);

        $customer->load([
            'type:id,code,name',
            'priceTier:id,code,name',
            'assignedSales:id,name',
            'photos.uploader:id,name',
            'geoPendings' => fn ($q) => $q->where('status', 'pending')->latest(),
            'geoPendings.capturedBy:id,name',
        ]);

        return Inertia::render('Customers/Show', [
            'customer' => $customer,
            'types' => CustomerType::query()->active()->orderBy('sort_order')->get(['id', 'code', 'name']),
            'tiers' => PriceTier::query()->active()->orderBy('sort_order')->get(['id', 'code', 'name']),
            'salesUsers' => User::query()
                ->whereHas('role', fn ($q) => $q->where('code', 'sales'))
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'outstanding' => [
                'total' => $this->outstanding->getTotal($customer),
                'aging' => $this->outstanding->getAging($customer),
                'invoices' => Invoice::query()
                    ->where('customer_id', $customer->id)
                    ->openOrPartial()
                    ->where('outstanding', '>', 0)
                    ->orderBy('due_date')
                    ->limit(20)
                    ->get([
                        'id', 'invoice_number', 'invoice_date', 'due_date',
                        'total', 'paid_amount', 'outstanding', 'status',
                    ]),
            ],
            'canUpdate' => request()->user()?->can('update', $customer) ?? false,
            'canDelete' => request()->user()?->can('delete', $customer) ?? false,
            'canApproveGeo' => request()->user()?->can('approveGeoPending', $customer) ?? false,
        ]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $this->service->update($customer, $request->validated());

        return back()->with('flash.success', 'Customer diperbarui.');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        $this->authorize('delete', $customer);

        $this->service->delete($customer);

        return redirect()
            ->route('customers.index')
            ->with('flash.success', "Customer {$customer->name} dihapus.");
    }

    public function toggleActive(Customer $customer): RedirectResponse
    {
        $this->authorize('toggleActive', $customer);

        $this->service->toggleActive($customer);

        return back()->with(
            'flash.success',
            $customer->is_active ? 'Customer diaktifkan.' : 'Customer dinonaktifkan.',
        );
    }

    public function reassignSales(ReassignSalesRequest $request, Customer $customer): RedirectResponse
    {
        $sales = User::query()->findOrFail($request->validated('assigned_sales_id'));

        $this->service->reassignSales($customer, $sales, $request->validated('reason'));

        return back()->with('flash.success', "Sales direassign ke {$sales->name}.");
    }

    public function outstanding(Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);

        return response()->json([
            'total' => $this->outstanding->getTotal($customer),
            'aging' => $this->outstanding->getAging($customer),
            'is_over_limit' => $this->outstanding->isOverLimit($customer),
        ]);
    }
}
