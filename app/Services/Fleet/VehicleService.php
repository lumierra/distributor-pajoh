<?php

namespace App\Services\Fleet;

use App\Models\Vehicle;
use App\Services\Numbering\NumberingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class VehicleService
{
    public function __construct(private readonly NumberingService $numbering) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Vehicle
    {
        $data['code'] = $this->numbering->next('vehicle_code');
        $data['plate_number'] = self::normalizePlate((string) ($data['plate_number'] ?? ''));

        $vehicle = Vehicle::create($data);

        $this->invalidateCache();

        return $vehicle;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Vehicle $vehicle, array $data): Vehicle
    {
        if (isset($data['plate_number'])) {
            $data['plate_number'] = self::normalizePlate((string) $data['plate_number']);
        }

        $vehicle->update($data);
        $this->invalidateCache();

        return $vehicle->refresh();
    }

    public function delete(Vehicle $vehicle): void
    {
        $blockers = $this->canBeDeleted($vehicle);

        if ($blockers !== []) {
            throw ValidationException::withMessages(['delete' => $blockers]);
        }

        $vehicle->delete();
        $this->invalidateCache();
    }

    /**
     * @return array<int, string>
     */
    public function canBeDeleted(Vehicle $vehicle): array
    {
        $blockers = [];

        if ($vehicle->status === Vehicle::STATUS_MAINTENANCE) {
            $blockers[] = 'Vehicle sedang maintenance — unset dulu sebelum hapus.';
        }

        // Stub — DO open check ditambah saat T12 landing.
        return $blockers;
    }

    public function toggleActive(Vehicle $vehicle): Vehicle
    {
        $vehicle->update(['is_active' => ! $vehicle->is_active]);
        $this->invalidateCache();

        return $vehicle->refresh();
    }

    public function setMaintenance(Vehicle $vehicle, ?string $notes = null): Vehicle
    {
        $vehicle->update([
            'status' => Vehicle::STATUS_MAINTENANCE,
            'notes' => $notes ?? $vehicle->notes,
        ]);
        $this->invalidateCache();

        return $vehicle->refresh();
    }

    public function unsetMaintenance(Vehicle $vehicle): Vehicle
    {
        $vehicle->update(['status' => Vehicle::STATUS_IDLE]);
        $this->invalidateCache();

        return $vehicle->refresh();
    }

    /**
     * Normalize plate: uppercase, collapse multiple spaces to single.
     * Example: "bl  9195   xx" → "BL 9195 XX".
     */
    public static function normalizePlate(string $plate): string
    {
        return mb_strtoupper(trim((string) preg_replace('/\s+/', ' ', $plate)));
    }

    private function invalidateCache(): void
    {
        Cache::forget('vehicles:available');
    }
}
