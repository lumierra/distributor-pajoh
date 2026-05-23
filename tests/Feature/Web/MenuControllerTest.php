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
    $this->seed(\Database\Seeders\RolePermissionSeeder::class);
});

function menuControllerUser(string $roleCode): User
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

test('admin tidak punya akses ke menu builder', function (): void {
    $admin = menuControllerUser(Role::CODE_ADMIN);

    $this->actingAs($admin)
        ->get(route('menus.index'))
        ->assertForbidden();
});

test('superadmin bisa lihat menu builder', function (): void {
    $super = menuControllerUser(Role::CODE_SUPERADMIN);

    $this->actingAs($super)
        ->get(route('menus.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Menus/Index'));
});

test('superadmin bisa edit metadata menu (label/order)', function (): void {
    $super = menuControllerUser(Role::CODE_SUPERADMIN);
    $menu = Menu::where('code', 'master.supplier')->first();

    $this->actingAs($super)
        ->put(route('menus.update', $menu->id), [
            'label' => 'Supplier Pajoh',
            'icon' => 'Factory',
            'order' => 99,
            'is_active' => true,
        ])
        ->assertRedirect();

    $menu->refresh();
    expect($menu->label)->toBe('Supplier Pajoh');
    expect($menu->order)->toBe(99);
});
