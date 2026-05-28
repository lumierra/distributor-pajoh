<?php

namespace App\Services\Delivery;

use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\DoItem;
use App\Models\Driver;
use App\Models\SalesOrder;
use App\Models\SoItem;
use App\Models\SoReservation;
use App\Models\StockLedger;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Services\Billing\InvoiceService;
use App\Services\CustomerReturn\CustomerReturnService;
use App\Services\Inventory\ReservationService;
use App\Services\Numbering\NumberingService;
use App\Services\Sales\SalesOrderService;
use App\Services\Setting\SettingManager;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DeliveryOrderService
{
    public function __construct(
        private readonly NumberingService $numbering,
        private readonly SettingManager $settings,
        private readonly ReservationService $reservation,
        private readonly SalesOrderService $salesOrder,
        private readonly InvoiceService $invoice,
        private readonly CustomerReturnService $customerReturn,
    ) {}

    /**
     * Create DO dari SO approved/partially_delivered. Auto-populate items
     * dari so_items × so_reservations (per batch).
     *
     * @param  array<int, array{so_item_id:int, qty_planned:int}>  $itemsConfig
     */
    public function createFromSo(SalesOrder $so, array $itemsConfig, User $by): DeliveryOrder
    {
        if (! in_array($so->status, [
            SalesOrder::STATUS_APPROVED,
            SalesOrder::STATUS_PARTIALLY_DELIVERED,
        ], true)) {
            throw ValidationException::withMessages([
                'sales_order_id' => "SO status `{$so->status}` tidak bisa di-DO — hanya approved/partially_delivered.",
            ]);
        }

        return DB::transaction(function () use ($so, $itemsConfig, $by): DeliveryOrder {
            $do = new DeliveryOrder([
                'sales_order_id' => $so->id,
                'customer_id' => $so->customer_id,
                'delivery_address_snapshot' => $this->buildAddressSnapshot($so->customer()->first()),
                'do_date' => now()->toDateString(),
                'expected_delivery_date' => $so->eta_date,
                'status' => DeliveryOrder::STATUS_DRAFT,
                'fiscal_year' => (int) now()->format('Y'),
                'created_by' => $by->id,
            ]);
            $do->do_number = $this->numbering->next('do');
            $do->save();

            $this->syncItems($do, $so, $itemsConfig);

            return $do->refresh();
        });
    }

    /**
     * Update DO draft (header + items).
     *
     * @param  array<int, array{so_item_id:int, qty_planned:int}>  $itemsConfig
     */
    public function updateDraft(DeliveryOrder $do, array $headerData, array $itemsConfig, User $by): DeliveryOrder
    {
        if (! $do->canBeEdited()) {
            throw ValidationException::withMessages(['status' => 'DO tidak bisa diedit pada status saat ini.']);
        }

        return DB::transaction(function () use ($do, $headerData, $itemsConfig, $by): DeliveryOrder {
            $doDate = $headerData['do_date'] instanceof CarbonInterface
                ? $headerData['do_date']
                : Carbon::parse($headerData['do_date']);

            $do->fill([
                'do_date' => $doDate,
                'expected_delivery_date' => $headerData['expected_delivery_date'] ?? null,
                'notes' => $headerData['notes'] ?? null,
                'fiscal_year' => (int) $doDate->format('Y'),
                'updated_by' => $by->id,
            ]);
            $do->save();

            $this->syncItems($do, $do->salesOrder, $itemsConfig);

            return $do->refresh();
        });
    }

    public function startPicking(DeliveryOrder $do, User $by): DeliveryOrder
    {
        if (! $do->canStartPicking()) {
            throw ValidationException::withMessages(['status' => 'DO tidak bisa start picking pada status saat ini.']);
        }

        $do->update([
            'status' => DeliveryOrder::STATUS_PICKING,
            'picking_started_at' => now(),
            'picking_started_by' => $by->id,
        ]);

        return $do->refresh();
    }

    /**
     * Confirm picked qty per item. Operator update bertahap.
     *
     * @param  array<int, array{item_id:int, qty_picked:int}>  $picks
     */
    public function confirmPicks(DeliveryOrder $do, array $picks, User $by): DeliveryOrder
    {
        if ($do->status !== DeliveryOrder::STATUS_PICKING) {
            throw ValidationException::withMessages(['status' => 'DO tidak dalam status picking.']);
        }

        DB::transaction(function () use ($do, $picks): void {
            $itemMap = $do->items()->get()->keyBy('id');

            foreach ($picks as $pick) {
                $item = $itemMap->get((int) $pick['item_id']);
                if ($item === null) {
                    throw ValidationException::withMessages([
                        'picks' => "DoItem #{$pick['item_id']} tidak tertaut ke DO ini.",
                    ]);
                }

                $qty = (int) $pick['qty_picked'];
                if ($qty < 0 || $qty > (int) $item->qty_planned) {
                    throw ValidationException::withMessages([
                        "picks.{$pick['item_id']}" => "qty_picked harus 0 sampai {$item->qty_planned}.",
                    ]);
                }

                $item->update(['qty_picked' => $qty]);
            }
        });

        return $do->refresh();
    }

    /**
     * Mark DO sebagai packed: validate driver/vehicle + snapshot.
     */
    public function markPacked(DeliveryOrder $do, int $driverId, int $vehicleId, User $by): DeliveryOrder
    {
        if (! $do->canMarkPacked()) {
            throw ValidationException::withMessages(['status' => 'DO tidak bisa mark packed pada status saat ini.']);
        }

        return DB::transaction(function () use ($do, $driverId, $vehicleId, $by): DeliveryOrder {
            /** @var Driver $driver */
            $driver = Driver::query()->findOrFail($driverId);
            /** @var Vehicle $vehicle */
            $vehicle = Vehicle::query()->findOrFail($vehicleId);

            if (! $driver->is_active) {
                throw ValidationException::withMessages(['driver_id' => 'Driver nonaktif.']);
            }
            if ($driver->status === Driver::STATUS_UNAVAILABLE) {
                throw ValidationException::withMessages(['driver_id' => 'Driver sedang unavailable.']);
            }
            if (! $vehicle->is_active) {
                throw ValidationException::withMessages(['vehicle_id' => 'Vehicle nonaktif.']);
            }
            if ($vehicle->status === Vehicle::STATUS_MAINTENANCE) {
                throw ValidationException::withMessages(['vehicle_id' => 'Vehicle sedang maintenance.']);
            }

            // Expired doc check (kalau setting aktif)
            if ((bool) $this->settings->get('vehicle.block_expired_doc.enabled', true)) {
                if ($driver->license_expired_date !== null
                    && $driver->license_expired_date->isPast()) {
                    throw ValidationException::withMessages([
                        'driver_id' => "SIM driver expired ({$driver->license_expired_date->format('d M Y')}).",
                    ]);
                }

                $expiredCriticalDoc = VehicleDocument::query()
                    ->where('vehicle_id', $vehicle->id)
                    ->whereIn('type', VehicleDocument::CRITICAL_TYPES)
                    ->whereNotNull('expires_date')
                    ->whereDate('expires_date', '<', now())
                    ->first();

                if ($expiredCriticalDoc !== null) {
                    throw ValidationException::withMessages([
                        'vehicle_id' => "Dokumen {$expiredCriticalDoc->type} vehicle expired.",
                    ]);
                }
            }

            $do->update([
                'status' => DeliveryOrder::STATUS_PACKED,
                'packed_at' => now(),
                'packed_by' => $by->id,
                'driver_id' => $driver->id,
                'vehicle_id' => $vehicle->id,
                'driver_snapshot' => [
                    'id' => $driver->id,
                    'code' => $driver->code,
                    'name' => $driver->name,
                    'phone' => $driver->phone,
                    'license_no' => $driver->license_no,
                    'license_type' => $driver->license_type,
                ],
                'vehicle_snapshot' => [
                    'id' => $vehicle->id,
                    'code' => $vehicle->code,
                    'plate' => $vehicle->plate_number,
                    'type' => $vehicle->type,
                    'brand' => $vehicle->brand,
                    'model' => $vehicle->model,
                ],
            ]);

            return $do->refresh();
        });
    }

    public function startDelivery(DeliveryOrder $do, User $by): DeliveryOrder
    {
        if (! $do->canStartDelivery()) {
            throw ValidationException::withMessages(['status' => 'DO tidak bisa start delivery pada status saat ini.']);
        }

        $do->update([
            'status' => DeliveryOrder::STATUS_IN_TRANSIT,
            'in_transit_at' => now(),
            'in_transit_by' => $by->id,
        ]);

        return $do->refresh();
    }

    /**
     * Mark delivered: input qty actual + foto bukti + consume reservation +
     * stock_ledger sale_out + update SO status.
     *
     * @param  array{
     *   receiver_name: string,
     *   receiver_notes?: string|null,
     *   latitude?: float|null,
     *   longitude?: float|null,
     *   item_quantities: array<int, array{item_id:int, qty_delivered:int, qty_returned?:int}>,
     * }  $data
     */
    public function markDelivered(
        DeliveryOrder $do,
        array $data,
        UploadedFile $proofPhoto,
        ?UploadedFile $signaturePhoto,
        User $by,
    ): DeliveryOrder {
        if (! $do->canMarkDelivered()) {
            throw ValidationException::withMessages(['status' => 'DO tidak dalam status in_transit.']);
        }

        return DB::transaction(function () use ($do, $data, $proofPhoto, $signaturePhoto, $by): DeliveryOrder {
            $do->load(['items.productUnit', 'salesOrder']);

            $proofPath = $this->storeProofPhoto($do, $proofPhoto, 'signed');
            $signaturePath = null;
            if ($signaturePhoto !== null) {
                $signaturePath = $this->storeProofPhoto($do, $signaturePhoto, 'signature');
            }

            // Update header
            $do->update([
                'status' => DeliveryOrder::STATUS_DELIVERED,
                'delivered_at' => now(),
                'delivered_by' => $by->id,
                'receiver_name' => $data['receiver_name'],
                'receiver_notes' => $data['receiver_notes'] ?? null,
                'proof_photo_signed_path' => $proofPath,
                'digital_signature_path' => $signaturePath,
                'delivery_latitude' => $data['latitude'] ?? null,
                'delivery_longitude' => $data['longitude'] ?? null,
            ]);

            // Index input qty per item_id
            $qtyMap = collect($data['item_quantities'] ?? [])
                ->keyBy(fn ($x) => (int) $x['item_id']);

            $hasPartialReturn = false;

            foreach ($do->items as $item) {
                $input = $qtyMap->get($item->id);
                $qtyDelivered = $input !== null
                    ? (int) ($input['qty_delivered'] ?? $item->qty_picked)
                    : (int) $item->qty_picked;
                $qtyReturned = $input !== null
                    ? (int) ($input['qty_returned'] ?? 0)
                    : 0;

                if ($qtyReturned > $qtyDelivered) {
                    throw ValidationException::withMessages([
                        "item_quantities.{$item->id}.qty_returned" => 'qty_returned tidak boleh > qty_delivered.',
                    ]);
                }

                $unit = $item->productUnit;
                $item->update([
                    'qty_delivered' => $qtyDelivered,
                    'qty_returned' => $qtyReturned,
                    'qty_delivered_base' => $qtyDelivered * (int) $unit->qty_to_base,
                    'qty_returned_base' => $qtyReturned * (int) $unit->qty_to_base,
                    'cost_price_base' => $this->getCostForBatch($item->batch_id),
                ]);

                if ($qtyReturned > 0) {
                    $hasPartialReturn = true;
                }

                // Consume reservation → ledger sale_out
                $this->reservation->consumeForDoItem($item->refresh(), $by);

                // Update so_items.qty_delivered (net = delivered - returned)
                $netDelivered = $qtyDelivered - $qtyReturned;
                if ($netDelivered > 0) {
                    SoItem::query()
                        ->where('id', $item->so_item_id)
                        ->increment('qty_delivered', $netDelivered);
                }
            }

            if ($hasPartialReturn) {
                $do->update([
                    'has_partial_return' => true,
                    'status' => DeliveryOrder::STATUS_PARTIAL_RETURNED,
                ]);
                $this->customerReturn->createFromDeliveryReject($do->refresh(), $by);
            }

            // Trigger SO status update
            $this->salesOrder->updateStatusAfterDo($do->salesOrder->refresh());

            // Auto-generate invoice (idempotent via unique do_id index).
            $this->invoice->generateFromDeliveredDo($do->refresh(), $by);

            return $do->refresh();
        });
    }

    public function cancel(DeliveryOrder $do, string $reason, User $by): DeliveryOrder
    {
        if (! $do->canBeCancelled()) {
            throw ValidationException::withMessages(['status' => 'DO tidak bisa di-cancel pada status saat ini.']);
        }

        // Reservation tetap aktif — SO masih punya hold untuk DO baru.
        $do->update([
            'status' => DeliveryOrder::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancelled_by' => $by->id,
            'cancel_reason' => $reason,
        ]);

        return $do->refresh();
    }

    private function getCostForBatch(?int $batchId): ?float
    {
        if ($batchId === null) {
            return null;
        }

        // Cost dari last purchase_in di batch tsb.
        return (float) (StockLedger::query()
            ->where('batch_id', $batchId)
            ->where('type', StockLedger::TYPE_PURCHASE_IN)
            ->latest('created_at')
            ->value('cost_price') ?? 0);
    }

    private function storeProofPhoto(DeliveryOrder $do, UploadedFile $file, string $kind): string
    {
        $ext = $file->getClientOriginalExtension() ?: 'jpg';
        $filename = Str::uuid()->toString().'.'.$ext;
        $year = $do->fiscal_year;
        $path = "delivery_orders/{$year}/{$do->id}/proof_{$kind}_{$filename}";

        Storage::disk('public')->putFileAs(
            dirname($path),
            $file,
            basename($path),
        );

        return $path;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAddressSnapshot(Customer $c): array
    {
        return [
            'name' => $c->name,
            'owner_name' => $c->owner_name,
            'phone' => $c->phone,
            'whatsapp' => $c->whatsapp,
            'address' => $c->address,
            'city' => $c->city,
            'province' => $c->province,
            'postal_code' => $c->postal_code,
            'area' => $c->area,
            'latitude' => $c->latitude,
            'longitude' => $c->longitude,
            'snapshotted_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Generate do_items dari reservation (per batch) — di-call dari
     * createFromSo & updateDraft.
     *
     * @param  array<int, array{so_item_id:int, qty_planned:int}>  $itemsConfig
     */
    private function syncItems(DeliveryOrder $do, SalesOrder $so, array $itemsConfig): void
    {
        $do->items()->delete();

        $so->loadMissing(['items.productUnit', 'reservations']);
        $soItemMap = $so->items->keyBy('id');

        foreach ($itemsConfig as $idx => $config) {
            $soItem = $soItemMap->get((int) $config['so_item_id']);
            if ($soItem === null) {
                throw ValidationException::withMessages([
                    "items.{$idx}.so_item_id" => 'so_item tidak tertaut ke SO ini.',
                ]);
            }

            $remainingDeliverable = (int) $soItem->qty - (int) $soItem->qty_delivered;
            $qtyPlanned = (int) $config['qty_planned'];

            if ($qtyPlanned < 1) {
                continue;
            }

            if ($qtyPlanned > $remainingDeliverable) {
                throw ValidationException::withMessages([
                    "items.{$idx}.qty_planned" => "qty_planned ({$qtyPlanned}) > sisa SO ({$remainingDeliverable}).",
                ]);
            }

            // Distribute qty per active reservation (FIFO order by reservation id).
            $reservations = SoReservation::query()
                ->where('so_item_id', $soItem->id)
                ->where('status', SoReservation::STATUS_ACTIVE)
                ->orderBy('id')
                ->get();

            $unit = $soItem->productUnit;
            $unitFactor = (int) $unit->qty_to_base;
            $remaining = $qtyPlanned;
            $sortOrder = $idx * 100;

            foreach ($reservations as $res) {
                if ($remaining <= 0) {
                    break;
                }

                $reservedInUom = (int) floor($res->qty_reserved / max(1, $unitFactor));
                if ($reservedInUom <= 0) {
                    continue;
                }

                $qtyForThis = min($reservedInUom, $remaining);

                $batch = $res->batch()->first();

                DoItem::create([
                    'delivery_order_id' => $do->id,
                    'so_item_id' => $soItem->id,
                    'reservation_id' => $res->id,
                    'product_id' => $soItem->product_id,
                    'supplier_id' => $soItem->supplier_id,
                    'product_unit_id' => $soItem->product_unit_id,
                    'product_name_snapshot' => $soItem->product_name_snapshot,
                    'product_sku_snapshot' => $soItem->product_sku_snapshot,
                    'product_unit_name_snapshot' => $soItem->product_unit_name_snapshot,
                    'batch_id' => $res->batch_id,
                    'batch_code_snapshot' => $batch?->batch_code,
                    'expired_date_snapshot' => $batch?->expired_date,
                    'qty_planned' => $qtyForThis,
                    'qty_planned_base' => $qtyForThis * $unitFactor,
                    'is_bonus' => (bool) $soItem->is_bonus,
                    'sort_order' => $sortOrder++,
                ]);

                $remaining -= $qtyForThis;
            }

            // Kalau masih ada remaining (tidak cukup reservation — mis. dari
            // SO yang ter-approve lewat allow_negative_stock), buat satu row
            // tanpa reservation_id (batch akan ditentukan manual / FIFO saat
            // markDelivered — di-defer; untuk MVP error saja).
            if ($remaining > 0) {
                throw ValidationException::withMessages([
                    "items.{$idx}.qty_planned" => "Reservasi tidak cukup ({$remaining} sisa). Cek active reservations.",
                ]);
            }
        }
    }
}
