<?php

use App\Models\Menu;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(\Database\Seeders\SettingSeeder::class);
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->seed(\Database\Seeders\MenuSeeder::class);
});

function menuPolicyMakeUser(string $roleCode): User
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

test('non-superadmin cannot create or update a menu', function (): void {
    $admin = menuPolicyMakeUser(Role::CODE_ADMIN);
    $menu = Menu::query()->where('code', 'master.supplier')->first();

    expect($admin->can('create', Menu::class))->toBeFalse();
    expect($admin->can('update', $menu))->toBeFalse();
});

test('superadmin can update a menu but cannot create new menu via UI', function (): void {
    $super = menuPolicyMakeUser(Role::CODE_SUPERADMIN);
    $menu = Menu::query()->where('code', 'master.supplier')->first();

    expect($super->can('update', $menu))->toBeTrue();
    expect($super->can('create', Menu::class))->toBeFalse();
});

test('system menu cannot be deleted — even by superadmin', function (): void {
    $super = menuPolicyMakeUser(Role::CODE_SUPERADMIN);
    $systemMenu = Menu::query()->where('code', 'dashboard')->first();

    expect($systemMenu->is_system)->toBeTrue();
    expect($super->can('delete', $systemMenu))->toBeFalse();
});

test('superadmin can delete a non-system menu', function (): void {
    $super = menuPolicyMakeUser(Role::CODE_SUPERADMIN);

    $custom = Menu::create([
        'code' => 'custom.example',
        'label' => 'Custom Example',
        'order' => 99,
        'is_active' => true,
        'is_system' => false,
    ]);

    expect($super->can('delete', $custom))->toBeTrue();
});
