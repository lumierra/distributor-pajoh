<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\LoginHistory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user()->load('role:id,code,name');

        $logins = LoginHistory::query()
            ->where('user_id', $user->id)
            ->orderByDesc('login_at')
            ->limit(20)
            ->get(['id', 'is_successful', 'failure_reason', 'ip', 'channel', 'login_at']);

        return Inertia::render('Profile/Show', [
            'user' => $user,
            'logins' => $logins,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:128'],
            'email' => ['nullable', 'email', 'max:128'],
            'phone' => ['nullable', 'string', 'max:32'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:64'],
            'emergency_contact_name' => ['nullable', 'string', 'max:128'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:32'],
        ]);

        $request->user()->update($data);

        return back()->with('flash.success', 'Profil disimpan.');
    }
}
