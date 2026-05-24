<?php

namespace App\Http\Controllers\Api\V1\Delivery;

use App\Http\Controllers\Controller;
use App\Http\Requests\Delivery\MarkDeliveredRequest;
use App\Models\DeliveryOrder;
use App\Services\Delivery\DeliveryOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint mobile untuk driver/sales:
 *  - List DO assigned ke driver
 *  - Detail DO
 *  - Start delivery (in_transit)
 *  - Mark delivered (foto + GPS + signature)
 */
class DeliveryOrderApiController extends Controller
{
    public function __construct(private readonly DeliveryOrderService $service) {}

    /**
     * GET /api/v1/sales/delivery-orders
     * List DO yang assigned ke driver/sales saat ini (status packed/in_transit).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = DeliveryOrder::query()
            ->with([
                'customer:id,code,name,phone,address,city,latitude,longitude',
                'salesOrder:id,so_number',
                'driver:id,name',
                'vehicle:id,plate_number',
                'items:id,delivery_order_id,product_name_snapshot,product_unit_name_snapshot,batch_code_snapshot,qty_planned,qty_picked,is_bonus',
            ])
            ->whereIn('status', [DeliveryOrder::STATUS_PACKED, DeliveryOrder::STATUS_IN_TRANSIT])
            ->orderByDesc('do_date');

        // Filter: kalau user adalah sales/driver, hanya tampilkan yang
        // ter-link via SO sales_id atau DO yang tidak punya constraint
        // khusus. Untuk MVP: tampilkan semua yang aksesible via policy.
        if (! $user->isSuperadmin() && $user->hasRole('sales')) {
            $query->whereHas('salesOrder', fn ($q) => $q->where('sales_id', $user->id));
        }

        return response()->json([
            'data' => $query->paginate(25),
        ]);
    }

    /**
     * GET /api/v1/sales/delivery-orders/{do}
     */
    public function show(Request $request, DeliveryOrder $deliveryOrder): JsonResponse
    {
        $deliveryOrder->load([
            'customer',
            'salesOrder:id,so_number,so_date',
            'driver',
            'vehicle',
            'items.batch:id,batch_code,expired_date',
        ]);

        return response()->json([
            'data' => $deliveryOrder,
            'can_start_delivery' => $deliveryOrder->canStartDelivery(),
            'can_mark_delivered' => $deliveryOrder->canMarkDelivered(),
        ]);
    }

    /**
     * POST /api/v1/sales/delivery-orders/{do}/start-delivery
     */
    public function startDelivery(Request $request, DeliveryOrder $deliveryOrder): JsonResponse
    {
        $this->authorize('startDelivery', $deliveryOrder);

        $this->service->startDelivery($deliveryOrder, $request->user());

        return response()->json([
            'message' => 'DO masuk in-transit.',
            'data' => $deliveryOrder->fresh(),
        ]);
    }

    /**
     * POST /api/v1/sales/delivery-orders/{do}/mark-delivered
     * multipart: receiver_name, receiver_notes, latitude, longitude,
     * proof_photo (file), digital_signature (file optional),
     * item_quantities[][item_id], item_quantities[][qty_delivered],
     * item_quantities[][qty_returned].
     */
    public function markDelivered(MarkDeliveredRequest $request, DeliveryOrder $deliveryOrder): JsonResponse
    {
        $data = $request->validated();

        $do = $this->service->markDelivered(
            $deliveryOrder,
            [
                'receiver_name' => $data['receiver_name'],
                'receiver_notes' => $data['receiver_notes'] ?? null,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'item_quantities' => $data['item_quantities'],
            ],
            $request->file('proof_photo'),
            $request->file('digital_signature'),
            $request->user(),
        );

        return response()->json([
            'message' => 'DO delivered.',
            'data' => $do,
        ]);
    }
}
