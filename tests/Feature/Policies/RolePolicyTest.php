<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(\Database\Seeders\SettingSeeder::class);
    $this->seed(\Database\Seeders\RoleSeeder::class);
});

function rolePolicyMakeUser(string $roleCode): User
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

test('admin cannot create or update or delete a role', function (): void {
    $admin = rolePolicyMakeUser(Role::CODE_ADMIN);
    $adminRole = Role::ofCode(Role::CODE_ADMIN)->first();

    expect($admin->can('create', Role::class))->toBeFalse();
    expect($admin->can('update', $adminRole))->toBeFalse();
    expect($admin->can('delete', $adminRole))->toBeFalse();
});

test('superadmin cannot update or delete a system role', function (): void {
    $super = rolePolicyMakeUser(Role::CODE_SUPERADMIN);
    $adminRole = Role::ofCode(Role::CODE_ADMIN)->first();

    expect($super->can('update', $adminRole))->toBeFalse();
    expect($super->can('delete', $adminRole))->toBeFalse();
});

test('superadmin cannot edit permissions of superadmin role', function (): void {
    $super = rolePolicyMakeUser(Role::CODE_SUPERADMIN);
    $superRole = Role::ofCode(Role::CODE_SUPERADMIN)->first();

    expect($super->can('updatePermissions', $superRole))->toBeFalse();
});

test('superadmin can edit permissions of non-superadmin roles', function (): void {
    $super = rolePolicyMakeUser(Role::CODE_SUPERADMIN);
    $kasirRole = Role::ofCode(Role::CODE_KASIR)->first();

    expect($super->can('updatePermissions', $kasirRole))->toBeTrue();
});

test('superadmin can create a custom role', function (): void {
    $super = rolePolicyMakeUser(Role::CODE_SUPERADMIN);

    expect($super->can('create', Role::class))->toBeTrue();
});

test('superadmin cannot delete a custom role that still has users', function (): void {
    $super = rolePolicyMakeUser(Role::CODE_SUPERADMIN);

    $custom = Role::create([
        'code' => 'qa_test',
        'name' => 'QA Test',
        'is_system' => false,
        'is_active' => true,
    ]);

    User::create([
        'name' => 'attached',
        'username' => 'attached_'.uniqid('', true),
        'password' => 'secret1234',
        'role_id' => $custom->id,
        'is_active' => true,
        'force_password_change' => false,
    ]);

    expect($super->can('delete', $custom))->toBeFalse();
});

test('superadmin can delete an unused custom role', function (): void {
    $super = rolePolicyMakeUser(Role::CODE_SUPERADMIN);

    $custom = Role::create([
        'code' => 'qa_empty',
        'name' => 'QA Empty',
        'is_system' => false,
        'is_active' => true,
    ]);

    expect($super->can('delete', $custom))->toBeTrue();
});
