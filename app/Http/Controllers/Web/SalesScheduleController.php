<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\SalesSchedule\StoreSalesScheduleRequest;
use App\Http\Requests\SalesSchedule\UpdateSalesScheduleRequest;
use App\Models\Customer;
use App\Models\Role;
use App\Models\SalesSchedule;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class SalesScheduleController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $this->authorize('viewAny', SalesSchedule::class);

        $query = SalesSchedule::query()
            ->with(['sales:id,name', 'customer:id,code,name'])
            ->orderBy('day_of_week')
            ->orderBy('sort_order');

        $user = $request->user();
        if ($user && ! $user->isSuperadmin() && $user->hasRole('sales')) {
            $query->where('sales_id', $user->id);
        }

        if ($salesId = $request->input('sales_id')) {
            $query->where('sales_id', $salesId);
        }
        if ($pattern = $request->input('pattern')) {
            $query->where('pattern', $pattern);
        }
        if ($customerId = $request->input('customer_id')) {
            $query->where('customer_id', $customerId);
        }

        return Inertia::render('SalesSchedules/Index', [
            'schedules' => $query->paginate(50)->withQueryString(),
            'filters' => [
                'sales_id' => $request->input('sales_id'),
                'pattern' => $request->input('pattern'),
                'customer_id' => $request->input('customer_id'),
            ],
            'salesUsers' => User::query()
                ->whereHas('role', fn ($q) => $q->where('code', Role::CODE_SALES))
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'customers' => Customer::query()->where('is_active', true)->orderBy('name')->limit(500)->get(['id', 'code', 'name']),
        ]);
    }

    public function store(StoreSalesScheduleRequest $request): RedirectResponse
    {
        $this->authorize('create', SalesSchedule::class);

        $schedule = SalesSchedule::create($request->validated());

        return redirect()
            ->route('sales-schedules.index')
            ->with('flash.success', "Schedule #{$schedule->id} dibuat.");
    }

    public function update(UpdateSalesScheduleRequest $request, SalesSchedule $salesSchedule): RedirectResponse
    {
        $this->authorize('update', $salesSchedule);

        $salesSchedule->update($request->validated());

        return back()->with('flash.success', 'Schedule diperbarui.');
    }

    public function destroy(SalesSchedule $salesSchedule): RedirectResponse
    {
        $this->authorize('delete', $salesSchedule);

        $salesSchedule->delete();

        return back()->with('flash.success', 'Schedule dihapus.');
    }
}
