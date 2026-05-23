<?php

use App\Models\Menu;
use App\Models\Role;
use App\Models\RoleMenu;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(\Database\Seeders\SettingSeeder::class);
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->seed(\Database\Seeders\MenuSeeder::class);
    $this->seed(\Database\Seeders\RolePermissionSeeder::class);
});

function roleControllerUser(string $roleCode): User
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

test('admin tidak bisa akses daftar role', function (): void {
    $admin = roleControllerUser(Role::CODE_ADMIN);

    $this->actingAs($admin)
        ->get(route('roles.index'))
        ->assertForbidden();
});

test('superadmin bisa lihat daftar role + jumlah user', function (): void {
    $super = roleControllerUser(Role::CODE_SUPERADMIN);

    $this->actingAs($super)
        ->get(route('roles.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Roles/Index')->has('roles'));
});

test('superadmin tidak bisa edit metadata system role', function (): void {
    $super = roleControllerUser(Role::CODE_SUPERADMIN);
    $adminRole = Role::ofCode(Role::CODE_ADMIN)->first();

    $this->actingAs($super)
        ->put(route('roles.update', $adminRole->id), [
            'code' => 'admin_renamed',
            'name' => 'Renamed',
            'is_active' => true,
        ])
        ->assertForbidden();
});

test('superadmin bisa create custom role baru', function (): void {
    $super = roleControllerUser(Role::CODE_SUPERADMIN);

    $this->actingAs($super)
        ->post(route('roles.store'), [
            'code' => 'qa_lead',
            'name' => 'QA Lead',
        ])
        ->assertRedirect();

    $role = Role::where('code', 'qa_lead')->first();
    expect($role)->not->toBeNull();
    expect($role->is_system)->toBeFalse();
});

test('superadmin tidak bisa edit permission superadmin role', function (): void {
    $super = roleControllerUser(Role::CODE_SUPERADMIN);
    $superRole = Role::ofCode(Role::CODE_SUPERADMIN)->first();

    $this->actingAs($super)
        ->get(route('roles.permissions.edit', $superRole->id))
        ->assertForbidden();
});

test('updatePermissions menyimpan matrix dan hapus row all-false', function (): void {
    $super = roleControllerUser(Role::CODE_SUPERADMIN);
    $kasirRole = Role::ofCode(Role::CODE_KASIR)->first();

    $menuPo = Menu::where('code', 'purchasing.po')->first();
    $menuInvoice = Menu::where('code', 'sales.invoice')->first();

    $this->actingAs($super)
        ->put(route('roles.permissions.update', $kasirRole->id), [
            'permissions' => [
                ['menu_id' => $menuPo->id, 'can_view' => true, 'can_create' => false, 'can_update' => false, 'can_delete' => false, 'can_approve' => false, 'can_export' => false],
                // all-false → harus dihapus
                ['menu_id' => $menuInvoice->id, 'can_view' => false, 'can_create' => false, 'can_update' => false, 'can_delete' => false, 'can_approve' => false, 'can_export' => false],
            ],
        ])
        ->assertRedirect();

    expect(RoleMenu::where('role_id', $kasirRole->id)->where('menu_id', $menuPo->id)->exists())->toBeTrue();
    expect(RoleMenu::where('role_id', $kasirRole->id)->where('menu_id', $menuInvoice->id)->exists())->toBeFalse();
});
