<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\SalesVisitBypassRequest;
use App\Services\Sales\BypassService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class SalesVisitBypassRequestController extends Controller
{
    public function __construct(private readonly BypassService $service) {}

    public function index(Request $request): InertiaResponse
    {
        $this->authorize('viewAny', SalesVisitBypassRequest::class);

        $query = SalesVisitBypassRequest::query()
            ->with(['sales:id,name', 'customer:id,code,name'])
            ->orderByDesc('requested_at');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        return Inertia::render('SalesVisitBypassRequests/Index', [
            'requests' => $query->paginate(50)->withQueryString(),
            'filters' => ['status' => $request->input('status')],
        ]);
    }

    public function approve(Request $request, SalesVisitBypassRequest $salesVisitBypassRequest): RedirectResponse
    {
        $this->authorize('review', $salesVisitBypassRequest);

        $this->service->approve($salesVisitBypassRequest, $request->user());

        return back()->with('flash.success', 'Bypass request di-approve. Valid 30 menit.');
    }

    public function reject(Request $request, SalesVisitBypassRequest $salesVisitBypassRequest): RedirectResponse
    {
        $this->authorize('review', $salesVisitBypassRequest);

        $data = $request->validate(['notes' => 'nullable|string|max:500']);

        $this->service->reject($salesVisitBypassRequest, $request->user(), $data['notes'] ?? null);

        return back()->with('flash.success', 'Bypass request di-reject.');
    }
}
