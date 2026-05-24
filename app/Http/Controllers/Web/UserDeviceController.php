<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserDevice\RejectDeviceRequest;
use App\Http\Requests\UserDevice\RevokeDeviceRequest;
use App\Models\UserDevice;
use App\Models\UserDevicePendingRequest;
use App\Services\Sales\DeviceBindingGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class UserDeviceController extends Controller
{
    public function __construct(private readonly DeviceBindingGuard $guard) {}

    public function index(Request $request): InertiaResponse
    {
        $this->authorize('viewAny', UserDevice::class);

        $devices = UserDevice::query()
            ->with(['user:id,name,username', 'registeredBy:id,name', 'revokedBy:id,name'])
            ->orderByDesc('last_login_at')
            ->paginate(25);

        $pending = UserDevicePendingRequest::query()
            ->with('user:id,name,username')
            ->pending()
            ->orderByDesc('requested_at')
            ->limit(50)
            ->get();

        return Inertia::render('UserDevices/Index', [
            'devices' => $devices,
            'pending' => $pending,
        ]);
    }

    public function approvePending(UserDevicePendingRequest $userDevicePendingRequest, Request $request): RedirectResponse
    {
        $this->authorize('approve', UserDevice::class);

        $this->guard->approvePending($userDevicePendingRequest, $request->user());

        return back()->with('flash.success', 'Device di-approve.');
    }

    public function rejectPending(RejectDeviceRequest $request, UserDevicePendingRequest $userDevicePendingRequest): RedirectResponse
    {
        $this->authorize('approve', UserDevice::class);

        $this->guard->rejectPending(
            $userDevicePendingRequest,
            $request->user(),
            $request->validated('rejection_reason'),
        );

        return back()->with('flash.success', 'Device request di-reject.');
    }

    public function revoke(RevokeDeviceRequest $request, UserDevice $userDevice): RedirectResponse
    {
        $this->authorize('revoke', $userDevice);

        $this->guard->revokeDevice(
            $userDevice,
            $request->user(),
            $request->validated('revoke_reason'),
        );

        return back()->with('flash.success', 'Device di-revoke.');
    }
}
