<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vehicle\SetMaintenanceRequest;
use App\Http\Requests\Vehicle\StoreVehicleRequest;
use App\Http\Requests\Vehicle\UpdateVehicleRequest;
use App\Models\Vehicle;
use App\Services\Fleet\VehicleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VehicleController extends Controller
{
    public function __construct(private readonly VehicleService $service) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Vehicle::class);

        $query = Vehicle::query()->orderBy('plate_number');

        if ($search = trim((string) $request->input('q'))) {
            $query->where(function ($q) use ($search): void {
                $q->where('plate_number', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        $totals = Vehicle::query()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS idle', [Vehicle::STATUS_IDLE])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS maintenance', [Vehicle::STATUS_MAINTENANCE])
            ->first();

        return Inertia::render('Vehicles/Index', [
            'vehicles' => $query->paginate(25)->withQueryString(),
            'types' => Vehicle::TYPES,
            'filters' => [
                'q' => $request->input('q'),
                'type' => $request->input('type'),
                'status' => $request->input('status'),
                'active' => $request->input('active'),
            ],
            'stats' => [
                'total' => (int) ($totals->total ?? 0),
                'active' => (int) ($totals->active ?? 0),
                'idle' => (int) ($totals->idle ?? 0),
                'maintenance' => (int) ($totals->maintenance ?? 0),
            ],
        ]);
    }

    public function store(StoreVehicleRequest $request): RedirectResponse
    {
        $vehicle = $this->service->create($request->validated());

        return back()->with('flash.success', "Vehicle {$vehicle->plate_number} ({$vehicle->code}) dibuat.");
    }

    public function update(UpdateVehicleRequest $request, Vehicle $vehicle): RedirectResponse
    {
        $this->service->update($vehicle, $request->validated());

        return back()->with('flash.success', 'Vehicle diperbarui.');
    }

    public function destroy(Vehicle $vehicle): RedirectResponse
    {
        $this->authorize('delete', $vehicle);

        $this->service->delete($vehicle);

        return redirect()
            ->route('vehicles.index')
            ->with('flash.success', "Vehicle {$vehicle->plate_number} dihapus.");
    }

    public function toggleActive(Vehicle $vehicle): RedirectResponse
    {
        $this->authorize('toggleActive', $vehicle);

        $this->service->toggleActive($vehicle);

        return back()->with(
            'flash.success',
            $vehicle->is_active ? 'Vehicle diaktifkan.' : 'Vehicle dinonaktifkan.',
        );
    }

    public function setMaintenance(SetMaintenanceRequest $request, Vehicle $vehicle): RedirectResponse
    {
        $this->service->setMaintenance($vehicle, $request->validated('notes'));

        return back()->with('flash.success', 'Vehicle di-set maintenance.');
    }

    public function unsetMaintenance(Vehicle $vehicle): RedirectResponse
    {
        $this->authorize('setMaintenance', $vehicle);

        $this->service->unsetMaintenance($vehicle);

        return back()->with('flash.success', 'Vehicle keluar dari maintenance.');
    }
}
