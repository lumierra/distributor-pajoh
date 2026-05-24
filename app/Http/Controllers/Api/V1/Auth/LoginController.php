<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\LoginHistory;
use App\Models\User;
use App\Services\Permission\PermissionResolver;
use App\Services\Sales\DeviceBindingGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    public function __construct(
        private readonly PermissionResolver $resolver,
        private readonly DeviceBindingGuard $deviceGuard,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:64'],
            'password' => ['required', 'string'],
            'device_uuid' => ['required', 'string', 'max:128'],
            'device_name' => ['required', 'string', 'max:128'],
            'device_model' => ['nullable', 'string', 'max:64'],
            'mac_address' => ['nullable', 'string', 'max:64'],
            'os' => ['nullable', 'string', 'max:32'],
            'os_version' => ['nullable', 'string', 'max:32'],
            'app_version' => ['nullable', 'string', 'max:32'],
        ]);

        $username = strtolower(trim($data['username']));
        $user = User::query()->where('username', $username)->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            $this->logAttempt($request, $user, $username, false, LoginHistory::FAIL_INVALID_CREDENTIALS, $data['device_uuid']);

            return response()->json(['message' => 'Username atau password salah.'], 401);
        }

        if (! $user->is_active) {
            $this->logAttempt($request, $user, $username, false, LoginHistory::FAIL_ACCOUNT_INACTIVE, $data['device_uuid']);

            return response()->json(['message' => 'Akun ini sudah dinonaktifkan.'], 403);
        }

        // Device binding check
        $bindingResult = $this->deviceGuard->resolveOnLogin($user, [
            'device_uuid' => $data['device_uuid'],
            'device_name' => $data['device_name'],
            'device_model' => $data['device_model'] ?? null,
            'mac_address' => $data['mac_address'] ?? null,
            'os' => $data['os'] ?? null,
            'os_version' => $data['os_version'] ?? null,
            'app_version' => $data['app_version'] ?? null,
            'ip' => $request->ip(),
        ]);

        if ($bindingResult['outcome'] === DeviceBindingGuard::OUTCOME_PENDING) {
            $this->logAttempt($request, $user, $username, false, 'device_pending', $data['device_uuid']);

            return response()->json([
                'message' => 'Device tidak terdaftar. Request approval telah dikirim ke admin.',
                'pending_request_id' => $bindingResult['pending']?->id,
            ], 403);
        }

        $token = $user->createToken(
            name: $data['device_name'],
            expiresAt: now()->addDays(30),
        )->plainTextToken;

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        $this->logAttempt($request, $user, $username, true, null, $data['device_uuid']);

        return response()->json([
            'token' => $token,
            'expires_at' => now()->addDays(30)->toIso8601String(),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'role' => $user->role->code,
                'force_password_change' => $user->force_password_change,
            ],
            'device' => $bindingResult['device'] ? [
                'id' => $bindingResult['device']->id,
                'status' => $bindingResult['device']->status,
                'outcome' => $bindingResult['outcome'],
            ] : null,
            'permissions' => $this->resolver->buildCache($user),
        ]);
    }

    private function logAttempt(
        Request $request,
        ?User $user,
        string $usernameInput,
        bool $success,
        ?string $failureReason,
        string $deviceUuid,
    ): void {
        LoginHistory::create([
            'user_id' => $user?->id,
            'username_input' => $usernameInput,
            'is_successful' => $success,
            'failure_reason' => $failureReason,
            'ip' => (string) ($request->ip() ?? '0.0.0.0'),
            'user_agent' => substr((string) $request->userAgent(), 0, 512),
            'device_uuid' => $deviceUuid,
            'channel' => LoginHistory::CHANNEL_MOBILE,
            'login_at' => now(),
        ]);
    }
}
