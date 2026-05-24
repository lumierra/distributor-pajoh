<?php

namespace App\Services\Sales;

use App\Models\Customer;
use App\Models\SalesSchedule;
use App\Models\SalesVisit;
use App\Models\SalesVisitBypassRequest;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SalesVisitService
{
    public function __construct(
        private readonly GeofenceValidator $geofence,
        private readonly FakeGpsDetector $fakeGps,
    ) {}

    /**
     * Get active visit for a user (cached helper for LinksToActiveVisit trait).
     */
    public function getActiveVisit(User $sales): ?SalesVisit
    {
        return SalesVisit::query()
            ->where('sales_id', $sales->id)
            ->where('status', SalesVisit::STATUS_ACTIVE)
            ->latest('checked_in_at')
            ->first();
    }

    /**
     * Check-in. Atomic: auto-checkout previous active visit + create new visit.
     *
     * @param  array{
     *   customer_id:int, latitude:float, longitude:float,
     *   accuracy_meter?:int|null, is_mock_location:bool, notes?:string|null,
     * }  $data
     */
    public function checkin(User $sales, array $data, ?UploadedFile $photo, ?UserDevice $device): SalesVisit
    {
        if ($this->fakeGps->shouldBlock((bool) $data['is_mock_location'])) {
            throw ValidationException::withMessages([
                'is_mock_location' => 'Mock location terdeteksi. Mohon matikan fake GPS.',
            ]);
        }

        /** @var Customer $customer */
        $customer = Customer::query()->findOrFail($data['customer_id']);

        $bypassActive = $this->hasActiveBypass($sales, $customer);
        $geoIssue = $this->geofence->checkAgainstCustomer(
            $customer,
            (float) $data['latitude'],
            (float) $data['longitude'],
        );

        if ($geoIssue !== null && ! $bypassActive) {
            throw ValidationException::withMessages([
                'location' => "Anda di luar radius outlet ({$geoIssue['distance']}m > {$geoIssue['radius']}m). Request bypass ke admin.",
            ]);
        }

        return DB::transaction(function () use ($sales, $customer, $data, $photo, $device, $bypassActive, $geoIssue): SalesVisit {
            // Auto-checkout previous active visit (sales bisa cuma 1 active)
            SalesVisit::query()
                ->where('sales_id', $sales->id)
                ->where('status', SalesVisit::STATUS_ACTIVE)
                ->get()
                ->each(function (SalesVisit $prev): void {
                    $prev->update([
                        'status' => SalesVisit::STATUS_COMPLETED,
                        'checked_out_at' => now(),
                        'duration_minutes' => max(0, (int) $prev->checked_in_at->diffInMinutes(now())),
                        'auto_checked_out' => true,
                        'auto_checkout_reason' => SalesVisit::AUTO_REASON_NEXT_VISIT,
                    ]);
                });

            $schedule = SalesSchedule::query()
                ->forSales($sales->id)
                ->where('customer_id', $customer->id)
                ->todayApplicable()
                ->first();

            $distanceToOutlet = null;
            if ($customer->latitude !== null && $customer->longitude !== null) {
                $distanceToOutlet = (int) round($this->geofence->distanceMeter(
                    (float) $data['latitude'],
                    (float) $data['longitude'],
                    (float) $customer->latitude,
                    (float) $customer->longitude,
                ));
            }

            $visit = SalesVisit::create([
                'sales_id' => $sales->id,
                'customer_id' => $customer->id,
                'schedule_id' => $schedule?->id,
                'device_id' => $device?->id,
                'checked_in_at' => now(),
                'checkin_latitude' => $data['latitude'],
                'checkin_longitude' => $data['longitude'],
                'checkin_accuracy_meter' => $data['accuracy_meter'] ?? null,
                'checkin_distance_to_outlet' => $distanceToOutlet,
                'checkin_notes' => $data['notes'] ?? null,
                'is_mock_location' => (bool) $data['is_mock_location'],
                'bypass_geofence' => $bypassActive && $geoIssue !== null,
                'status' => SalesVisit::STATUS_ACTIVE,
            ]);

            if ($photo !== null) {
                $path = $this->storePhoto($visit, $photo);
                $visit->update(['checkin_photo_path' => $path]);
            }

            return $visit->refresh();
        });
    }

    /**
     * @param  array{latitude?:float|null, longitude?:float|null, notes?:string|null}  $data
     */
    public function checkout(SalesVisit $visit, User $sales, array $data): SalesVisit
    {
        if ($visit->status !== SalesVisit::STATUS_ACTIVE) {
            throw ValidationException::withMessages(['status' => 'Visit sudah completed/cancelled.']);
        }
        if ($visit->sales_id !== $sales->id && ! $sales->isSuperadmin()) {
            throw ValidationException::withMessages(['status' => 'Tidak boleh checkout visit user lain.']);
        }

        $duration = max(0, (int) $visit->checked_in_at->diffInMinutes(now()));

        $visit->update([
            'status' => SalesVisit::STATUS_COMPLETED,
            'checked_out_at' => now(),
            'checkout_latitude' => $data['latitude'] ?? null,
            'checkout_longitude' => $data['longitude'] ?? null,
            'checkout_notes' => $data['notes'] ?? null,
            'duration_minutes' => $duration,
        ]);

        $visit->refresh()->recomputeAggregates();

        return $visit->refresh();
    }

    public function cancel(SalesVisit $visit, User $by, string $reason): SalesVisit
    {
        if ($visit->status !== SalesVisit::STATUS_ACTIVE) {
            throw ValidationException::withMessages(['status' => 'Visit hanya bisa di-cancel saat active.']);
        }

        // Block kalau ada SO/retur sudah dibuat di visit ini
        if ($visit->salesOrders()->exists() || $visit->customerReturns()->exists()) {
            throw ValidationException::withMessages([
                'status' => 'Tidak bisa cancel — sudah ada SO/Retur di visit ini.',
            ]);
        }

        $visit->update([
            'status' => SalesVisit::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancel_reason' => $reason,
        ]);

        return $visit->refresh();
    }

    public function hasActiveBypass(User $sales, Customer $customer): bool
    {
        return SalesVisitBypassRequest::query()
            ->where('sales_id', $sales->id)
            ->where('customer_id', $customer->id)
            ->approvedNotExpired()
            ->exists();
    }

    private function storePhoto(SalesVisit $visit, UploadedFile $photo): string
    {
        $ext = $photo->getClientOriginalExtension() ?: 'jpg';
        $filename = Str::uuid()->toString().'.'.$ext;
        $year = now()->format('Y');
        $path = "visits/{$year}/{$visit->id}/checkin.{$ext}";

        Storage::disk('public')->putFileAs(dirname($path), $photo, basename($path));

        return $path;
    }
}
