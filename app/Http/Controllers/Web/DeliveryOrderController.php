<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Delivery\CancelDeliveryOrderRequest;
use App\Http\Requests\Delivery\ConfirmPicksRequest;
use App\Http\Requests\Delivery\MarkDeliveredRequest;
use App\Http\Requests\Delivery\MarkPackedRequest;
use App\Http\Requests\Delivery\StoreDeliveryOrderRequest;
use App\Http\Requests\Delivery\UpdateDeliveryOrderRequest;
use App\Models\DeliveryOrder;
use App\Models\Driver;
use App\Models\SalesOrder;
use App\Models\Vehicle;
use App\Services\Delivery\DeliveryOrderService;
use App\Services\Delivery\DoPdfRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class DeliveryOrderController extends Controller
{
    public function __construct(
        private readonly DeliveryOrderService $service,
        private readonly DoPdfRenderer $pdf,
    ) {}

    public function index(Request $request): InertiaResponse
    {
        $this->authorize('viewAny', DeliveryOrder::class);

        $query = DeliveryOrder::query()
            ->with([
                'salesOrder:id,so_number',
                'customer:id,code,name',
                'driver:id,code,name',
                'vehicle:id,code,plate_number',
            ])
            ->orderByDesc('do_date')
            ->orderByDesc('id');

        if ($search = trim((string) $request->input('q'))) {
            $query->where(function ($q) use ($search): void {
                $q->where('do_number', 'like', "%{$search}%")
                    ->orWhereHas('salesOrder', fn ($s) => $s->where('so_number', 'like', "%{$search}%"))
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$search}%"));
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($driverId = $request->input('driver_id')) {
            $query->where('driver_id', $driverId);
        }

        if ($vehicleId = $request->input('vehicle_id')) {
            $query->where('vehicle_id', $vehicleId);
        }

        $totals = DeliveryOrder::query()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS draft', [DeliveryOrder::STATUS_DRAFT])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS picking', [DeliveryOrder::STATUS_PICKING])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS packed', [DeliveryOrder::STATUS_PACKED])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS in_transit', [DeliveryOrder::STATUS_IN_TRANSIT])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS delivered', [DeliveryOrder::STATUS_DELIVERED])
            ->first();

        return Inertia::render('DeliveryOrders/Index', [
            'deliveryOrders' => $query->paginate(25)->withQueryString(),
            'drivers' => Driver::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'vehicles' => Vehicle::query()->where('is_active', true)->orderBy('plate_number')->get(['id', 'code', 'plate_number']),
            'filters' => [
                'q' => $request->input('q'),
                'status' => $request->input('status'),
                'driver_id' => $request->input('driver_id'),
                'vehicle_id' => $request->input('vehicle_id'),
            ],
            'stats' => [
                'total' => (int) ($totals->total ?? 0),
                'draft' => (int) ($totals->draft ?? 0),
                'picking' => (int) ($totals->picking ?? 0),
                'packed' => (int) ($totals->packed ?? 0),
                'in_transit' => (int) ($totals->in_transit ?? 0),
                'delivered' => (int) ($totals->delivered ?? 0),
            ],
        ]);
    }

    public function create(Request $request): InertiaResponse
    {
        $this->authorize('create', DeliveryOrder::class);

        $openSos = SalesOrder::query()
            ->whereIn('status', [SalesOrder::STATUS_APPROVED, SalesOrder::STATUS_PARTIALLY_DELIVERED])
            ->with(['customer:id,code,name', 'items'])
            ->orderByDesc('so_date')
            ->limit(100)
            ->get(['id', 'so_number', 'so_date', 'customer_id', 'status']);

        return Inertia::render('DeliveryOrders/Create', [
            'openSalesOrders' => $openSos,
            'selectedSo' => $request->input('sales_order_id')
                ? $this->loadSoForDo((int) $request->input('sales_order_id'))
                : null,
        ]);
    }

    public function store(StoreDeliveryOrderRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $so = SalesOrder::query()->findOrFail($data['sales_order_id']);

        $do = $this->service->createFromSo(
            $so,
            $data['items'],
            $request->user(),
        );

        // Set expected_delivery_date kalau diisi
        if (! empty($data['expected_delivery_date'])) {
            $do->update(['expected_delivery_date' => $data['expected_delivery_date']]);
        }
        if (! empty($data['notes'])) {
            $do->update(['notes' => $data['notes']]);
        }

        return redirect()
            ->route('delivery-orders.show', $do)
            ->with('flash.success', "DO {$do->do_number} dibuat (draft).");
    }

    public function show(DeliveryOrder $deliveryOrder): InertiaResponse
    {
        $this->authorize('view', $deliveryOrder);

        $deliveryOrder->load([
            'salesOrder:id,so_number,so_date,customer_id',
            'salesOrder.customer:id,code,name',
            'customer:id,code,name,phone,address,city',
            'items.soItem:id,qty,qty_delivered',
            'items.batch:id,batch_code,expired_date',
            'driver:id,code,name,phone,license_no,license_type',
            'vehicle:id,code,plate_number,type,brand,model',
            'pickingStarter:id,name',
            'packer:id,name',
            'inTransitStarter:id,name',
            'deliverer:id,name',
            'canceller:id,name',
        ]);

        return Inertia::render('DeliveryOrders/Show', [
            'deliveryOrder' => $deliveryOrder,
            'drivers' => Driver::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name', 'license_expired_date']),
            'vehicles' => Vehicle::query()->where('is_active', true)->orderBy('plate_number')->get(['id', 'code', 'plate_number', 'type']),
            'canEdit' => $deliveryOrder->canBeEdited() && (request()->user()?->can('update', $deliveryOrder) ?? false),
            'canStartPicking' => $deliveryOrder->canStartPicking() && (request()->user()?->can('startPicking', $deliveryOrder) ?? false),
            'canConfirmPick' => $deliveryOrder->status === DeliveryOrder::STATUS_PICKING && (request()->user()?->can('confirmPick', $deliveryOrder) ?? false),
            'canMarkPacked' => $deliveryOrder->canMarkPacked() && (request()->user()?->can('markPacked', $deliveryOrder) ?? false),
            'canStartDelivery' => $deliveryOrder->canStartDelivery() && (request()->user()?->can('startDelivery', $deliveryOrder) ?? false),
            'canMarkDelivered' => $deliveryOrder->canMarkDelivered() && (request()->user()?->can('markDelivered', $deliveryOrder) ?? false),
            'canCancel' => $deliveryOrder->canBeCancelled() && (request()->user()?->can('cancel', $deliveryOrder) ?? false),
        ]);
    }

    public function edit(DeliveryOrder $deliveryOrder): InertiaResponse
    {
        $this->authorize('update', $deliveryOrder);
        abort_unless($deliveryOrder->canBeEdited(), 422, 'DO tidak bisa diedit.');

        $deliveryOrder->load(['salesOrder', 'items']);

        return Inertia::render('DeliveryOrders/Edit', [
            'deliveryOrder' => $deliveryOrder,
            'so' => $this->loadSoForDo($deliveryOrder->sales_order_id),
        ]);
    }

    public function update(UpdateDeliveryOrderRequest $request, DeliveryOrder $deliveryOrder): RedirectResponse
    {
        $data = $request->validated();
        $items = $data['items'];
        unset($data['items']);

        $this->service->updateDraft($deliveryOrder, $data, $items, $request->user());

        return redirect()
            ->route('delivery-orders.show', $deliveryOrder)
            ->with('flash.success', 'DO draft diperbarui.');
    }

    public function destroy(DeliveryOrder $deliveryOrder): RedirectResponse
    {
        $this->authorize('delete', $deliveryOrder);

        $deliveryOrder->delete();

        return redirect()->route('delivery-orders.index');
    }

    public function startPicking(Request $request, DeliveryOrder $deliveryOrder): RedirectResponse
    {
        $this->authorize('startPicking', $deliveryOrder);

        $this->service->startPicking($deliveryOrder, $request->user());

        return back()->with('flash.success', "DO {$deliveryOrder->do_number} masuk fase picking.");
    }

    public function confirmPicks(ConfirmPicksRequest $request, DeliveryOrder $deliveryOrder): RedirectResponse
    {
        $this->service->confirmPicks($deliveryOrder, $request->validated('picks'), $request->user());

        return back()->with('flash.success', 'Qty picked diperbarui.');
    }

    public function markPacked(MarkPackedRequest $request, DeliveryOrder $deliveryOrder): RedirectResponse
    {
        $do = $this->service->markPacked(
            $deliveryOrder,
            (int) $request->validated('driver_id'),
            (int) $request->validated('vehicle_id'),
            $request->user(),
        );

        // Auto-generate PDF surat jalan
        $this->pdf->generate($do);

        return back()->with('flash.success', "DO {$do->do_number} di-pack. Surat jalan ter-generate.");
    }

    public function startDelivery(Request $request, DeliveryOrder $deliveryOrder): RedirectResponse
    {
        $this->authorize('startDelivery', $deliveryOrder);

        $this->service->startDelivery($deliveryOrder, $request->user());

        return back()->with('flash.success', "DO {$deliveryOrder->do_number} in-transit.");
    }

    public function markDelivered(MarkDeliveredRequest $request, DeliveryOrder $deliveryOrder): RedirectResponse
    {
        $data = $request->validated();

        $this->service->markDelivered(
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

        return back()->with('flash.success', "DO {$deliveryOrder->do_number} delivered. Stok ter-decrement.");
    }

    public function cancel(CancelDeliveryOrderRequest $request, DeliveryOrder $deliveryOrder): RedirectResponse
    {
        $this->service->cancel($deliveryOrder, $request->validated('cancel_reason'), $request->user());

        return back()->with('flash.success', "DO {$deliveryOrder->do_number} dibatalkan.");
    }

    public function downloadPdf(DeliveryOrder $deliveryOrder): Response
    {
        $this->authorize('view', $deliveryOrder);

        abort_if(
            $deliveryOrder->pdf_path === null || ! Storage::disk('local')->exists($deliveryOrder->pdf_path),
            404,
            'PDF belum tersedia. Mark packed untuk generate.',
        );

        return response()->file(
            Storage::disk('local')->path($deliveryOrder->pdf_path),
            ['Content-Type' => 'application/pdf'],
        );
    }

    /**
     * AJAX: detail SO + items untuk operator pilih qty_planned.
     */
    public function soDetails(SalesOrder $salesOrder): JsonResponse
    {
        $this->authorize('create', DeliveryOrder::class);

        return response()->json([
            'so' => $this->loadSoForDo($salesOrder->id),
        ]);
    }

    private function loadSoForDo(int $soId): ?array
    {
        $so = SalesOrder::query()
            ->with([
                'customer:id,code,name,address,city',
                'items',
            ])
            ->find($soId);

        if ($so === null) {
            return null;
        }

        return [
            'id' => $so->id,
            'so_number' => $so->so_number,
            'so_date' => $so->so_date?->toDateString(),
            'eta_date' => $so->eta_date?->toDateString(),
            'status' => $so->status,
            'customer' => $so->customer,
            'items' => $so->items->map(fn ($i) => [
                'id' => $i->id,
                'product_id' => $i->product_id,
                'product_unit_id' => $i->product_unit_id,
                'product_name' => $i->product_name_snapshot,
                'product_sku' => $i->product_sku_snapshot,
                'unit_name' => $i->product_unit_name_snapshot,
                'qty' => (int) $i->qty,
                'qty_delivered' => (int) $i->qty_delivered,
                'qty_remaining' => max(0, (int) $i->qty - (int) $i->qty_delivered),
                'is_bonus' => (bool) $i->is_bonus,
            ])->values(),
        ];
    }
}
