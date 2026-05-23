<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(\Database\Seeders\SettingSeeder::class);
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->seed(\Database\Seeders\MenuSeeder::class);
    $this->seed(\Database\Seeders\RolePermissionSeeder::class);
});

function menuMiddlewareUser(string $roleCode): User
{
    $uniq = uniqid('', true);

    return User::create([
        'name' => 'U-'.$roleCode.'-'.$uniq,
        'username' => 'u_'.$roleCode.'_'.$uniq,
        'password' => 'secret1234',
        'role_id' => Role::ofCode($roleCode)->value('id'),
        'is_active' => true,
        'force_password_change' => false,
    ]);
}

function defineMenuRoute(string $menuCode = 'master.supplier'): void
{
    Route::middleware(['web', 'auth', 'menu:'.$menuCode])
        ->get('/_test/menu-access', fn () => response('ok'))
        ->name('_test.menu-access');
}

test('user with permission can access the route', function (): void {
    defineMenuRoute('master.supplier');

    $admin = menuMiddlewareUser(Role::CODE_ADMIN);

    $this->actingAs($admin)
        ->get('/_test/menu-access')
        ->assertOk();
});

test('user without permission gets 403', function (): void {
    defineMenuRoute('master.supplier');

    $kasir = menuMiddlewareUser(Role::CODE_KASIR);

    $this->actingAs($kasir)
        ->get('/_test/menu-access')
        ->assertForbidden();
});

test('superadmin always passes through', function (): void {
    defineMenuRoute('master.supplier');

    $super = menuMiddlewareUser(Role::CODE_SUPERADMIN);

    $this->actingAs($super)
        ->get('/_test/menu-access')
        ->assertOk();
});

test('unknown menu code returns 404', function (): void {
    defineMenuRoute('does.not.exist');

    $admin = menuMiddlewareUser(Role::CODE_ADMIN);

    $this->actingAs($admin)
        ->get('/_test/menu-access')
        ->assertNotFound();
});

test('unauthenticated request is redirected to login', function (): void {
    defineMenuRoute('master.supplier');

    $this->get('/_test/menu-access')->assertRedirect(route('login'));
});
