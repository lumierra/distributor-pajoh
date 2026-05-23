<?php

namespace App\Services\Fleet;

use App\Models\Driver;
use App\Services\Numbering\NumberingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class DriverService
{
    public function __construct(private readonly NumberingService $numbering) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Driver
    {
        $data['code'] = $this->numbering->next('driver_code');
        $driver = Driver::create($data);

        $this->invalidateCache();

        return $driver;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Driver $driver, array $data): Driver
    {
        $driver->update($data);
        $this->invalidateCache();

        return $driver->refresh();
    }

    public function delete(Driver $driver): void
    {
        $blockers = $this->canBeDeleted($driver);

        if ($blockers !== []) {
            throw ValidationException::withMessages(['delete' => $blockers]);
        }

        $driver->delete();
        $this->invalidateCache();
    }

    /**
     * @return array<int, string>
     */
    public function canBeDeleted(Driver $driver): array
    {
        // Stub — DO open check ditambah saat T12 landing.
        return [];
    }

    public function toggleActive(Driver $driver): Driver
    {
        $driver->update(['is_active' => ! $driver->is_active]);
        $this->invalidateCache();

        return $driver->refresh();
    }

    public function setUnavailable(Driver $driver): Driver
    {
        $driver->update(['status' => Driver::STATUS_UNAVAILABLE]);
        $this->invalidateCache();

        return $driver->refresh();
    }

    public function setIdle(Driver $driver): Driver
    {
        $driver->update(['status' => Driver::STATUS_IDLE]);
        $this->invalidateCache();

        return $driver->refresh();
    }

    private function invalidateCache(): void
    {
        Cache::forget('drivers:available');
    }
}
