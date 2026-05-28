<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Driver\StoreDriverRequest;
use App\Http\Requests\Driver\UpdateDriverRequest;
use App\Models\Driver;
use App\Models\Vehicle;
use App\Services\Fleet\DriverService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DriverController extends Controller
{
    public function __construct(private readonly DriverService $service) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Driver::class);

        $query = Driver::query()
            ->with(['defaultVehicle:id,code,plate_number'])
            ->orderBy('name');

        if ($search = trim((string) $request->input('q'))) {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('whatsapp', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        $totals = Driver::query()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS idle', [Driver::STATUS_IDLE])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS on_delivery', [Driver::STATUS_ON_DELIVERY])
            ->first();

        return Inertia::render('Drivers/Index', [
            'drivers' => $query->paginate(25)->withQueryString(),
            'vehicles' => Vehicle::query()->active()->orderBy('plate_number')->get(['id', 'code', 'plate_number']),
            'filters' => [
                'q' => $request->input('q'),
                'status' => $request->input('status'),
                'active' => $request->input('active'),
            ],
            'stats' => [
                'total' => (int) ($totals->total ?? 0),
                'active' => (int) ($totals->active ?? 0),
                'idle' => (int) ($totals->idle ?? 0),
                'on_delivery' => (int) ($totals->on_delivery ?? 0),
            ],
        ]);
    }

    public function store(StoreDriverRequest $request): RedirectResponse
    {
        $driver = $this->service->create($request->validated());

        return back()->with('flash.success', "Driver {$driver->name} ({$driver->code}) dibuat.");
    }

    public function update(UpdateDriverRequest $request, Driver $driver): RedirectResponse
    {
        $this->service->update($driver, $request->validated());

        return back()->with('flash.success', 'Driver diperbarui.');
    }

    public function destroy(Driver $driver): RedirectResponse
    {
        $this->authorize('delete', $driver);

        $this->service->delete($driver);

        return redirect()
            ->route('drivers.index')
            ->with('flash.success', "Driver {$driver->name} dihapus.");
    }

    public function toggleActive(Driver $driver): RedirectResponse
    {
        $this->authorize('toggleActive', $driver);

        $this->service->toggleActive($driver);

        return back()->with(
            'flash.success',
            $driver->is_active ? 'Driver diaktifkan.' : 'Driver dinonaktifkan.',
        );
    }

    public function setUnavailable(Driver $driver): RedirectResponse
    {
        $this->authorize('setUnavailable', $driver);

        if ($driver->status === Driver::STATUS_UNAVAILABLE) {
            $this->service->setIdle($driver);

            return back()->with('flash.success', 'Driver kembali tersedia.');
        }

        $this->service->setUnavailable($driver);

        return back()->with('flash.success', 'Driver di-set unavailable.');
    }
}
