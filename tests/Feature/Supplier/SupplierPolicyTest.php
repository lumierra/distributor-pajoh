<?php

use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Supplier\SupplierService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(\Database\Seeders\SettingSeeder::class);
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->seed(\Database\Seeders\MenuSeeder::class);
    $this->seed(\Database\Seeders\RolePermissionSeeder::class);
});

function supplierPolicyUser(string $roleCode): User
{
    $uniq = uniqid('', true);

    return User::create([
        'name' => 'U-'.$roleCode.'-'.$uniq,
        'username' => 'u_'.$roleCode.'_'.str_replace('.', '', $uniq),
        'password' => 'secret1234',
        'role_id' => Role::ofCode($roleCode)->value('id'),
        'is_active' => true,
        'force_password_change' => false,
    ]);
}

test('superadmin bypass: bisa semua action', function (): void {
    $super = supplierPolicyUser(Role::CODE_SUPERADMIN);
    $supplier = app(SupplierService::class)->create(['name' => 'PT X']);

    expect($super->can('viewAny', Supplier::class))->toBeTrue();
    expect($super->can('create', Supplier::class))->toBeTrue();
    expect($super->can('update', $supplier))->toBeTrue();
    expect($super->can('delete', $supplier))->toBeTrue();
});

test('admin: view+create+update granted, delete denied', function (): void {
    $admin = supplierPolicyUser(Role::CODE_ADMIN);
    $supplier = app(SupplierService::class)->create(['name' => 'PT Y']);

    expect($admin->can('viewAny', Supplier::class))->toBeTrue();
    expect($admin->can('create', Supplier::class))->toBeTrue();
    expect($admin->can('update', $supplier))->toBeTrue();
    expect($admin->can('delete', $supplier))->toBeFalse();
});

test('operator: hanya view (sesuai matrix RolePermissionSeeder)', function (): void {
    $operator = supplierPolicyUser(Role::CODE_OPERATOR);
    $supplier = app(SupplierService::class)->create(['name' => 'PT Z']);

    // Operator tidak punya entry master.supplier di matrix → semua false
    expect($operator->can('viewAny', Supplier::class))->toBeFalse();
    expect($operator->can('create', Supplier::class))->toBeFalse();
    expect($operator->can('update', $supplier))->toBeFalse();
    expect($operator->can('delete', $supplier))->toBeFalse();
});

test('kasir & sales: no access default', function (): void {
    $kasir = supplierPolicyUser(Role::CODE_KASIR);
    $sales = supplierPolicyUser(Role::CODE_SALES);
    $supplier = app(SupplierService::class)->create(['name' => 'PT W']);

    expect($kasir->can('viewAny', Supplier::class))->toBeFalse();
    expect($sales->can('viewAny', Supplier::class))->toBeFalse();
    expect($kasir->can('view', $supplier))->toBeFalse();
    expect($sales->can('view', $supplier))->toBeFalse();
});
