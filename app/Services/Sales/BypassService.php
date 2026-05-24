<?php

namespace App\Services\Sales;

use App\Models\Customer;
use App\Models\SalesVisitBypassRequest;
use App\Models\User;
use App\Services\Setting\SettingManager;
use Illuminate\Validation\ValidationException;

class BypassService
{
    public function __construct(private readonly SettingManager $settings) {}

    /**
     * Sales request bypass geofence.
     *
     * @param  array{reason:string, requested_lat?:float|null, requested_lng?:float|null, distance_meter?:int|null}  $data
     */
    public function requestBypass(User $sales, Customer $customer, array $data): SalesVisitBypassRequest
    {
        return SalesVisitBypassRequest::create([
            'sales_id' => $sales->id,
            'customer_id' => $customer->id,
            'reason' => $data['reason'],
            'requested_lat' => $data['requested_lat'] ?? null,
            'requested_lng' => $data['requested_lng'] ?? null,
            'distance_meter' => $data['distance_meter'] ?? null,
            'requested_at' => now(),
            'status' => SalesVisitBypassRequest::STATUS_PENDING,
        ]);
    }

    public function approve(SalesVisitBypassRequest $request, User $admin): SalesVisitBypassRequest
    {
        if ($request->status !== SalesVisitBypassRequest::STATUS_PENDING) {
            throw ValidationException::withMessages(['status' => 'Request sudah di-review.']);
        }

        $validMinutes = (int) $this->settings->get('sales.geofence.bypass_valid_minutes', 30);

        $request->update([
            'status' => SalesVisitBypassRequest::STATUS_APPROVED,
            'reviewed_at' => now(),
            'reviewed_by' => $admin->id,
            'expires_at' => now()->addMinutes($validMinutes),
        ]);

        return $request->refresh();
    }

    public function reject(SalesVisitBypassRequest $request, User $admin, ?string $notes = null): SalesVisitBypassRequest
    {
        if ($request->status !== SalesVisitBypassRequest::STATUS_PENDING) {
            throw ValidationException::withMessages(['status' => 'Request sudah di-review.']);
        }

        $request->update([
            'status' => SalesVisitBypassRequest::STATUS_REJECTED,
            'reviewed_at' => now(),
            'reviewed_by' => $admin->id,
            'notes' => $notes,
        ]);

        return $request->refresh();
    }

    /**
     * Expire requests yang sudah > 30 menit pending tanpa review.
     * Dipanggil dari scheduled job (hourly).
     */
    public function expireStalePending(): int
    {
        $threshold = (int) $this->settings->get('sales.geofence.bypass_pending_expire_minutes', 30);
        $cutoff = now()->subMinutes($threshold);

        $rows = SalesVisitBypassRequest::query()
            ->pending()
            ->where('requested_at', '<', $cutoff)
            ->get();

        foreach ($rows as $req) {
            $req->update([
                'status' => SalesVisitBypassRequest::STATUS_EXPIRED,
                'reviewed_at' => now(),
            ]);
        }

        return $rows->count();
    }
}
