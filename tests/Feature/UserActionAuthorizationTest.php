<?php

use App\Models\Role;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\SuperadminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([
        SettingSeeder::class,
        RoleSeeder::class,
        MenuSeeder::class,
        RolePermissionSeeder::class,
        SuperadminSeeder::class,
    ]);
});

test('unauthorized inertia request redirects back with flash.error instead of rendering an error page', function (): void {
    $superadmin = User::query()->whereHas('role', fn ($q) => $q->where('code', Role::CODE_SUPERADMIN))->firstOrFail();

    // Superadmin tidak boleh suspend dirinya sendiri (UserPolicy::toggleActive).
    $response = $this->actingAs($superadmin)
        ->withHeaders(['X-Inertia' => 'true', 'Referer' => route('users.index')])
        ->post(route('users.toggle-active', $superadmin));

    $response->assertRedirect(route('users.index'));
    $response->assertSessionHas('flash.error');
});

test('unauthorized non-inertia request still renders the default error page', function (): void {
    $superadmin = User::query()->whereHas('role', fn ($q) => $q->where('code', Role::CODE_SUPERADMIN))->firstOrFail();

    $response = $this->actingAs($superadmin)
        ->post(route('users.toggle-active', $superadmin));

    $response->assertStatus(403);
});
