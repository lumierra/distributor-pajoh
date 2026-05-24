<?php

namespace App\Services\Sales;

use App\Models\User;
use App\Models\UserDevice;
use App\Models\UserDevicePendingRequest;
use App\Services\Setting\SettingManager;
use Illuminate\Support\Facades\DB;

/**
 * Device binding for mobile login.
 *
 * Outcome enum:
 *   `OUTCOME_ALLOW`        — login boleh lanjut, device sudah aktif (refresh last_login)
 *   `OUTCOME_AUTO_REGISTER`— first device auto-registered (kalau setting on)
 *   `OUTCOME_PENDING`      — device baru, request approval ke admin
 *   `OUTCOME_DISABLED`     — binding disabled, skip device check
 */
class DeviceBindingGuard
{
    public const OUTCOME_ALLOW = 'allow';

    public const OUTCOME_AUTO_REGISTER = 'auto_register';

    public const OUTCOME_PENDING = 'pending';

    public const OUTCOME_DISABLED = 'disabled';

    public function __construct(private readonly SettingManager $settings) {}

    /**
     * Resolve device binding di login mobile.
     *
     * @param  array<string, mixed>  $payload  device_uuid, device_name, mac_address, os, os_version, app_version, ip
     * @return array{outcome:string, device:?UserDevice, pending:?UserDevicePendingRequest}
     */
    public function resolveOnLogin(User $user, array $payload): array
    {
        $bindingEnabled = (bool) $this->settings->get('sales.device.binding_enabled', true);

        if (! $bindingEnabled || ! $user->role || $user->role->code !== 'sales') {
            return ['outcome' => self::OUTCOME_DISABLED, 'device' => null, 'pending' => null];
        }

        $deviceUuid = (string) $payload['device_uuid'];

        $existing = UserDevice::query()
            ->where('user_id', $user->id)
            ->where('device_uuid', $deviceUuid)
            ->first();

        if ($existing !== null && $existing->isActive()) {
            $existing->update([
                'last_login_at' => now(),
                'last_login_ip' => $payload['ip'] ?? null,
                'mac_address' => $payload['mac_address'] ?? $existing->mac_address,
                'app_version' => $payload['app_version'] ?? $existing->app_version,
            ]);

            return ['outcome' => self::OUTCOME_ALLOW, 'device' => $existing, 'pending' => null];
        }

        $activeCount = UserDevice::query()
            ->where('user_id', $user->id)
            ->where('status', UserDevice::STATUS_ACTIVE)
            ->count();

        $autoRegister = (bool) $this->settings->get('sales.device.auto_register_first', true);

        if ($activeCount === 0 && $autoRegister) {
            $device = UserDevice::create([
                'user_id' => $user->id,
                'device_uuid' => $deviceUuid,
                'mac_address' => $payload['mac_address'] ?? null,
                'device_name' => $payload['device_name'] ?? null,
                'device_model' => $payload['device_model'] ?? null,
                'os' => $payload['os'] ?? null,
                'os_version' => $payload['os_version'] ?? null,
                'app_version' => $payload['app_version'] ?? null,
                'status' => UserDevice::STATUS_ACTIVE,
                'registered_at' => now(),
                'last_login_at' => now(),
                'last_login_ip' => $payload['ip'] ?? null,
            ]);

            return ['outcome' => self::OUTCOME_AUTO_REGISTER, 'device' => $device, 'pending' => null];
        }

        // Device baru, butuh approval admin
        $pending = UserDevicePendingRequest::create([
            'user_id' => $user->id,
            'device_uuid' => $deviceUuid,
            'mac_address' => $payload['mac_address'] ?? null,
            'device_name' => $payload['device_name'] ?? null,
            'device_model' => $payload['device_model'] ?? null,
            'os' => $payload['os'] ?? null,
            'os_version' => $payload['os_version'] ?? null,
            'app_version' => $payload['app_version'] ?? null,
            'requested_ip' => $payload['ip'] ?? null,
            'requested_at' => now(),
            'status' => UserDevicePendingRequest::STATUS_PENDING,
        ]);

        return ['outcome' => self::OUTCOME_PENDING, 'device' => null, 'pending' => $pending];
    }

    /**
     * Admin approve pending request → activate new device.
     * Kalau max_per_user=1, revoke device active sebelumnya.
     */
    public function approvePending(UserDevicePendingRequest $request, User $admin): UserDevice
    {
        if ($request->status !== UserDevicePendingRequest::STATUS_PENDING) {
            throw new \RuntimeException('Pending request sudah di-review.');
        }

        return DB::transaction(function () use ($request, $admin): UserDevice {
            $maxPerUser = (int) $this->settings->get('sales.device.max_per_user', 1);

            if ($maxPerUser === 1) {
                UserDevice::query()
                    ->where('user_id', $request->user_id)
                    ->where('status', UserDevice::STATUS_ACTIVE)
                    ->update([
                        'status' => UserDevice::STATUS_REVOKED,
                        'revoked_at' => now(),
                        'revoked_by' => $admin->id,
                        'revoke_reason' => 'Replaced by new device approval',
                    ]);
            }

            $device = UserDevice::create([
                'user_id' => $request->user_id,
                'device_uuid' => $request->device_uuid,
                'mac_address' => $request->mac_address,
                'device_name' => $request->device_name,
                'device_model' => $request->device_model,
                'os' => $request->os,
                'os_version' => $request->os_version,
                'app_version' => $request->app_version,
                'status' => UserDevice::STATUS_ACTIVE,
                'registered_at' => now(),
                'registered_by' => $admin->id,
            ]);

            $request->update([
                'status' => UserDevicePendingRequest::STATUS_APPROVED,
                'reviewed_at' => now(),
                'reviewed_by' => $admin->id,
                'activated_device_id' => $device->id,
            ]);

            return $device;
        });
    }

    public function rejectPending(UserDevicePendingRequest $request, User $admin, string $reason): void
    {
        $request->update([
            'status' => UserDevicePendingRequest::STATUS_REJECTED,
            'reviewed_at' => now(),
            'reviewed_by' => $admin->id,
            'rejection_reason' => $reason,
        ]);
    }

    public function revokeDevice(UserDevice $device, User $admin, string $reason): void
    {
        $device->update([
            'status' => UserDevice::STATUS_REVOKED,
            'revoked_at' => now(),
            'revoked_by' => $admin->id,
            'revoke_reason' => $reason,
        ]);
    }
}
