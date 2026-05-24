<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\SalesVisit\CancelVisitRequest;
use App\Models\Role;
use App\Models\SalesVisit;
use App\Models\User;
use App\Services\Sales\SalesVisitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class SalesVisitController extends Controller
{
    public function __construct(private readonly SalesVisitService $service) {}

    public function index(Request $request): InertiaResponse
    {
        $this->authorize('viewAny', SalesVisit::class);

        $query = SalesVisit::query()
            ->with(['sales:id,name', 'customer:id,code,name', 'schedule:id,pattern'])
            ->orderByDesc('checked_in_at');

        $user = $request->user();
        if ($user && ! $user->isSuperadmin() && $user->hasRole('sales')) {
            $query->where('sales_id', $user->id);
        }

        if ($salesId = $request->input('sales_id')) {
            $query->where('sales_id', $salesId);
        }
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($from = $request->input('from')) {
            $query->whereDate('checked_in_at', '>=', $from);
        }
        if ($to = $request->input('to')) {
            $query->whereDate('checked_in_at', '<=', $to);
        }

        $totals = SalesVisit::query()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS active', [SalesVisit::STATUS_ACTIVE])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS completed', [SalesVisit::STATUS_COMPLETED])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS cancelled', [SalesVisit::STATUS_CANCELLED])
            ->selectRaw('SUM(CASE WHEN auto_checked_out = 1 THEN 1 ELSE 0 END) AS auto_checkout')
            ->first();

        return Inertia::render('SalesVisits/Index', [
            'visits' => $query->paginate(25)->withQueryString(),
            'filters' => [
                'sales_id' => $request->input('sales_id'),
                'status' => $request->input('status'),
                'from' => $request->input('from'),
                'to' => $request->input('to'),
            ],
            'salesUsers' => User::query()
                ->whereHas('role', fn ($q) => $q->where('code', Role::CODE_SALES))
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'stats' => [
                'total' => (int) ($totals->total ?? 0),
                'active' => (int) ($totals->active ?? 0),
                'completed' => (int) ($totals->completed ?? 0),
                'cancelled' => (int) ($totals->cancelled ?? 0),
                'auto_checkout' => (int) ($totals->auto_checkout ?? 0),
            ],
        ]);
    }

    public function show(SalesVisit $salesVisit): InertiaResponse
    {
        $this->authorize('view', $salesVisit);

        $salesVisit->load([
            'sales:id,name',
            'customer:id,code,name,phone,address,latitude,longitude',
            'schedule:id,pattern,day_of_week,visit_date',
            'device:id,device_name,os,os_version',
            'salesOrders:id,visit_id,so_number,total,status',
            'customerReturns:id,visit_id,return_number,total_value,status',
        ]);

        $user = request()->user();

        return Inertia::render('SalesVisits/Show', [
            'visit' => $salesVisit,
            'canCancel' => $user?->can('cancel', $salesVisit) ?? false,
            'canCheckout' => $user?->can('checkout', $salesVisit) ?? false,
        ]);
    }

    public function cancel(CancelVisitRequest $request, SalesVisit $salesVisit): RedirectResponse
    {
        $this->authorize('cancel', $salesVisit);

        $this->service->cancel($salesVisit, $request->user(), $request->validated('reason'));

        return back()->with('flash.success', 'Visit di-cancel.');
    }
}
