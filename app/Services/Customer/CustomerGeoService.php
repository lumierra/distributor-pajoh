<?php

namespace App\Services\Customer;

use App\Models\Customer;
use App\Models\CustomerGeoPending;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CustomerGeoService
{
    public function setManual(Customer $customer, float $lat, float $lng, User $by): void
    {
        $customer->update([
            'latitude' => $lat,
            'longitude' => $lng,
            'geo_confirmed_at' => now(),
            'geo_confirmed_by' => $by->id,
        ]);
    }

    public function capturePending(
        Customer $customer,
        float $lat,
        float $lng,
        User $sales,
        ?int $visitId = null,
        ?int $accuracy = null,
    ): CustomerGeoPending {
        return CustomerGeoPending::create([
            'customer_id' => $customer->id,
            'visit_id' => $visitId,
            'captured_latitude' => $lat,
            'captured_longitude' => $lng,
            'captured_at' => now(),
            'captured_by' => $sales->id,
            'accuracy_meter' => $accuracy,
            'status' => CustomerGeoPending::STATUS_PENDING,
        ]);
    }

    public function approve(CustomerGeoPending $pending, User $by): void
    {
        DB::transaction(function () use ($pending, $by): void {
            $pending->customer->update([
                'latitude' => $pending->captured_latitude,
                'longitude' => $pending->captured_longitude,
                'geo_confirmed_at' => now(),
                'geo_confirmed_by' => $by->id,
                'geo_captured_by' => $pending->captured_by,
            ]);

            $pending->update([
                'status' => CustomerGeoPending::STATUS_APPROVED,
                'reviewed_at' => now(),
                'reviewed_by' => $by->id,
            ]);

            // Auto-reject sibling pending (rule 3.4)
            CustomerGeoPending::query()
                ->where('customer_id', $pending->customer_id)
                ->where('id', '!=', $pending->id)
                ->where('status', CustomerGeoPending::STATUS_PENDING)
                ->update([
                    'status' => CustomerGeoPending::STATUS_REJECTED,
                    'reviewed_at' => now(),
                    'reviewed_by' => $by->id,
                    'rejection_reason' => 'Sudah ada koordinat dari pending lain.',
                    'updated_at' => now(),
                ]);
        });
    }

    public function reject(CustomerGeoPending $pending, string $reason, User $by): void
    {
        $pending->update([
            'status' => CustomerGeoPending::STATUS_REJECTED,
            'reviewed_at' => now(),
            'reviewed_by' => $by->id,
            'rejection_reason' => $reason,
        ]);
    }

    public function distanceMeter(Customer $customer, float $lat, float $lng): ?float
    {
        return $customer->distanceTo($lat, $lng);
    }
}
