<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(\Database\Seeders\SettingSeeder::class);
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->seed(\Database\Seeders\MenuSeeder::class);
    $this->seed(\Database\Seeders\RolePermissionSeeder::class);
});

function policyMakeUser(string $roleCode, array $overrides = []): User
{
    $uniq = uniqid('', true);

    return User::create(array_merge([
        'name' => 'U-'.$roleCode.'-'.$uniq,
        'username' => 'u_'.$roleCode.'_'.$uniq,
        'password' => 'secret1234',
        'role_id' => Role::ofCode($roleCode)->value('id'),
        'is_active' => true,
        'force_password_change' => false,
    ], $overrides));
}

test('superadmin can view + update another user', function (): void {
    $super = policyMakeUser(Role::CODE_SUPERADMIN);
    $admin = policyMakeUser(Role::CODE_ADMIN);

    expect($super->can('view', $admin))->toBeTrue();
    expect($super->can('update', $admin))->toBeTrue();
});

test('admin cannot view or edit another superadmin', function (): void {
    $admin = policyMakeUser(Role::CODE_ADMIN);
    $otherSuper = policyMakeUser(Role::CODE_SUPERADMIN);

    expect($admin->can('view', $otherSuper))->toBeFalse();
    expect($admin->can('update', $otherSuper))->toBeFalse();
    expect($admin->can('delete', $otherSuper))->toBeFalse();
});

test('user can always view themselves regardless of role', function (): void {
    $sales = policyMakeUser(Role::CODE_SALES);

    expect($sales->can('view', $sales))->toBeTrue();
});

test('nobody can delete themselves — not even superadmin', function (): void {
    $super = policyMakeUser(Role::CODE_SUPERADMIN);

    expect($super->can('delete', $super))->toBeFalse();
});

test('kasir cannot view user list', function (): void {
    $kasir = policyMakeUser(Role::CODE_KASIR);

    expect($kasir->can('viewAny', User::class))->toBeFalse();
});

test('admin can view user list', function (): void {
    $admin = policyMakeUser(Role::CODE_ADMIN);

    expect($admin->can('viewAny', User::class))->toBeTrue();
});

test('admin cannot reset password of a superadmin', function (): void {
    $admin = policyMakeUser(Role::CODE_ADMIN);
    $super = policyMakeUser(Role::CODE_SUPERADMIN);

    expect($admin->can('resetPassword', $super))->toBeFalse();
});

test('superadmin cannot reset their own password via this action', function (): void {
    $super = policyMakeUser(Role::CODE_SUPERADMIN);

    expect($super->can('resetPassword', $super))->toBeFalse();
});

test('updateMenuOverrides is allowed only for superadmin acting on a non-superadmin', function (): void {
    $super = policyMakeUser(Role::CODE_SUPERADMIN);
    $admin = policyMakeUser(Role::CODE_ADMIN);
    $anotherSuper = policyMakeUser(Role::CODE_SUPERADMIN);

    expect($super->can('updateMenuOverrides', $admin))->toBeTrue();
    expect($super->can('updateMenuOverrides', $anotherSuper))->toBeFalse();
    expect($admin->can('updateMenuOverrides', $admin))->toBeFalse();
});
