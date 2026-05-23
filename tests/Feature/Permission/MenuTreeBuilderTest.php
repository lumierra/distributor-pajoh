<?php

use App\Models\Role;
use App\Models\User;
use App\Services\Permission\MenuTreeBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(\Database\Seeders\SettingSeeder::class);
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->seed(\Database\Seeders\MenuSeeder::class);
    $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    $this->builder = app(MenuTreeBuilder::class);
});

function buildFor(string $roleCode): array
{
    $user = User::create([
        'name' => 'U-'.$roleCode,
        'username' => 'u_'.$roleCode,
        'password' => 'secret1234',
        'role_id' => Role::ofCode($roleCode)->value('id'),
        'is_active' => true,
        'force_password_change' => false,
    ]);

    return app(MenuTreeBuilder::class)->build($user);
}

test('superadmin sees every top-level group', function (): void {
    $tree = buildFor(Role::CODE_SUPERADMIN);

    $codes = collect($tree)->pluck('code')->all();
    expect($codes)->toContain('master', 'sales', 'settings', 'audit');
});

test('kasir tree only contains groups with at least one visible child', function (): void {
    $tree = buildFor(Role::CODE_KASIR);

    $codes = collect($tree)->pluck('code')->all();
    expect($codes)->toContain('finance', 'reports', 'sales');
    expect($codes)->not->toContain('master', 'audit');
});

test('children list only includes accessible menus', function (): void {
    $tree = buildFor(Role::CODE_KASIR);

    $finance = collect($tree)->firstWhere('code', 'finance');
    expect($finance)->not->toBeNull();

    $childCodes = collect($finance['children'])->pluck('code')->all();
    expect($childCodes)->toContain('finance.payment', 'finance.payment_request');
    expect($childCodes)->not->toContain('finance.extension');
});

test('parent without any visible children but with its own route still appears', function (): void {
    // 'dashboard' is a top-level menu with a route — every role can view it.
    $tree = buildFor(Role::CODE_SALES);

    $codes = collect($tree)->pluck('code')->all();
    expect($codes)->toContain('dashboard');
});
