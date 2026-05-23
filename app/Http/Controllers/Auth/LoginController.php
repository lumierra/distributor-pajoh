<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Auth/Login', [
            'companyName' => setting('company.name'),
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->validated();
        $remember = (bool) ($credentials['remember'] ?? false);

        $user = User::query()
            ->where('username', $credentials['username'])
            ->first();

        if (! $user || ! Auth::attempt(
            ['username' => $credentials['username'], 'password' => $credentials['password']],
            $remember,
        )) {
            $this->logAttempt($request, $user, false, LoginHistory::FAIL_INVALID_CREDENTIALS);

            throw ValidationException::withMessages([
                'username' => 'Username atau password salah.',
            ]);
        }

        if (! $user->is_active) {
            Auth::logout();
            $this->logAttempt($request, $user, false, LoginHistory::FAIL_ACCOUNT_INACTIVE);

            throw ValidationException::withMessages([
                'username' => 'Akun ini sudah dinonaktifkan.',
            ]);
        }

        $request->session()->regenerate();

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        $this->logAttempt($request, $user, true, null);

        if ($user->force_password_change) {
            return redirect()->route('password.change.show');
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function logAttempt(Request $request, ?User $user, bool $success, ?string $failureReason): void
    {
        LoginHistory::create([
            'user_id' => $user?->id,
            'username_input' => (string) $request->input('username'),
            'is_successful' => $success,
            'failure_reason' => $failureReason,
            'ip' => (string) ($request->ip() ?? '0.0.0.0'),
            'user_agent' => substr((string) $request->userAgent(), 0, 512),
            'channel' => LoginHistory::CHANNEL_WEB,
            'login_at' => now(),
        ]);
    }
}
