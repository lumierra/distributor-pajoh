<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Services\Auth\PasswordPolicyValidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ChangePasswordController extends Controller
{
    public function __construct(private readonly PasswordPolicyValidator $policy) {}

    public function show(): Response
    {
        return Inertia::render('Auth/ChangePassword', [
            'forced' => (bool) auth()->user()?->force_password_change,
        ]);
    }

    public function store(ChangePasswordRequest $request): RedirectResponse
    {
        $user = $request->user();
        $newPassword = (string) $request->input('new_password');

        $result = $this->policy->check(
            $newPassword,
            $user,
            (string) $request->input('current_password'),
        );

        if (! $result['valid']) {
            throw ValidationException::withMessages([
                'new_password' => $result['errors'],
            ]);
        }

        $this->policy->recordHistory($user, $newPassword);

        $user->forceFill([
            'password' => $newPassword,            // hashed via cast
            'force_password_change' => false,
            'password_changed_at' => now(),
        ])->save();

        return redirect()->route('dashboard')->with('flash.success', 'Password berhasil diubah.');
    }
}
