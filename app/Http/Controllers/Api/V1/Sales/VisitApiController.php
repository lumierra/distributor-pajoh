<?php

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\SalesVisit\CheckinRequest;
use App\Http\Requests\SalesVisit\CheckoutRequest;
use App\Models\SalesVisit;
use App\Models\UserDevice;
use App\Services\Sales\SalesVisitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VisitApiController extends Controller
{
    public function __construct(private readonly SalesVisitService $service) {}

    public function active(Request $request): JsonResponse
    {
        $user = $request->user();
        $visit = $this->service->getActiveVisit($user);

        if ($visit === null) {
            return response()->json(['active' => null]);
        }

        $visit->load('customer:id,code,name,address,latitude,longitude');

        return response()->json([
            'active' => [
                'id' => $visit->id,
                'customer' => $visit->customer,
                'checked_in_at' => $visit->checked_in_at?->toIso8601String(),
                'duration_minutes' => $visit->duration(),
                'so_count' => $visit->so_count,
                'so_total_value' => (float) $visit->so_total_value,
            ],
        ]);
    }

    public function checkin(CheckinRequest $request): JsonResponse
    {
        $user = $request->user();
        $device = $this->resolveDevice($request);

        $visit = $this->service->checkin(
            $user,
            $request->validated(),
            $request->hasFile('photo') ? $request->file('photo') : null,
            $device,
        );

        return response()->json([
            'visit_id' => $visit->id,
            'status' => $visit->status,
            'checked_in_at' => $visit->checked_in_at?->toIso8601String(),
        ], 201);
    }

    public function checkout(CheckoutRequest $request, SalesVisit $visit): JsonResponse
    {
        $user = $request->user();

        $visit = $this->service->checkout($visit, $user, $request->validated());

        return response()->json([
            'visit_id' => $visit->id,
            'status' => $visit->status,
            'checked_out_at' => $visit->checked_out_at?->toIso8601String(),
            'duration_minutes' => $visit->duration_minutes,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $visits = SalesVisit::query()
            ->where('sales_id', $user->id)
            ->orderByDesc('checked_in_at')
            ->with('customer:id,code,name')
            ->limit(50)
            ->get(['id', 'customer_id', 'checked_in_at', 'checked_out_at', 'status', 'so_count', 'duration_minutes']);

        return response()->json(['visits' => $visits]);
    }

    private function resolveDevice(Request $request): ?UserDevice
    {
        $uuid = $request->header('X-Device-UUID');
        if (! $uuid) {
            return null;
        }

        return UserDevice::query()
            ->where('user_id', $request->user()->id)
            ->where('device_uuid', $uuid)
            ->where('status', UserDevice::STATUS_ACTIVE)
            ->first();
    }
}
