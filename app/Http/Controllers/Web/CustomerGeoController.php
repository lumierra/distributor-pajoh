<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\SetGeoRequest;
use App\Models\Customer;
use App\Models\CustomerGeoPending;
use App\Services\Customer\CustomerGeoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CustomerGeoController extends Controller
{
    public function __construct(private readonly CustomerGeoService $service) {}

    public function update(SetGeoRequest $request, Customer $customer): RedirectResponse
    {
        $this->service->setManual(
            $customer,
            (float) $request->validated('latitude'),
            (float) $request->validated('longitude'),
            $request->user(),
        );

        return back()->with('flash.success', 'Koordinat outlet diperbarui.');
    }

    public function approvePending(Customer $customer, CustomerGeoPending $pending): RedirectResponse
    {
        $this->authorize('approveGeoPending', $customer);
        abort_if($pending->customer_id !== $customer->id, 404);

        $this->service->approve($pending, request()->user());

        return back()->with('flash.success', 'Koordinat pending disetujui.');
    }

    public function rejectPending(Request $request, Customer $customer, CustomerGeoPending $pending): RedirectResponse
    {
        $this->authorize('approveGeoPending', $customer);
        abort_if($pending->customer_id !== $customer->id, 404);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $this->service->reject($pending, $data['reason'], $request->user());

        return back()->with('flash.success', 'Koordinat pending ditolak.');
    }
}
