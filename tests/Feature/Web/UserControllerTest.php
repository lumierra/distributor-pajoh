<?php

use App\Models\PasswordResetLog;
use App\Models\Role;
use App\Models\User;
use App\Models\UserMenuOverride;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(\Database\Seeders\SettingSeeder::class);
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->seed(\Database\Seeders\MenuSeeder::class);
    $this->seed(\Database\Seeders\RolePermissionSeeder::class);
});

function userControllerUser(string $roleCode, array $overrides = []): User
{
    $uniq = uniqid('', true);

    return User::create(array_merge([
        'name' => 'U-'.$roleCode.'-'.$uniq,
        'username' => 'u_'.$roleCode.'_'.str_replace('.', '', $uniq),
        'password' => 'secret1234',
        'role_id' => Role::ofCode($roleCode)->value('id'),
        'is_active' => true,
        'force_password_change' => false,
    ], $overrides));
}

test('kasir tidak bisa akses daftar user', function (): void {
    $kasir = userControllerUser(Role::CODE_KASIR);

    $this->actingAs($kasir)
        ->get(route('users.index'))
        ->assertForbidden();
});

test('admin bisa lihat daftar user', function (): void {
    $admin = userControllerUser(Role::CODE_ADMIN);

    $this->actingAs($admin)
        ->get(route('users.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Users/Index'));
});

test('admin gagal create user dengan role superadmin', function (): void {
    $admin = userControllerUser(Role::CODE_ADMIN);
    $superRoleId = Role::ofCode(Role::CODE_SUPERADMIN)->value('id');

    $this->actingAs($admin)
        ->post(route('users.store'), [
            'name' => 'Hack',
            'username' => 'hackrole',
            'role_id' => $superRoleId,
            'password' => 'newpass1234',
            'password_confirmation' => 'newpass1234',
            'is_active' => true,
        ])
        ->assertSessionHasErrors('role_id');
});

test('superadmin bisa create user dengan role superadmin', function (): void {
    $super = userControllerUser(Role::CODE_SUPERADMIN);
    $superRoleId = Role::ofCode(Role::CODE_SUPERADMIN)->value('id');

    $this->actingAs($super)
        ->post(route('users.store'), [
            'name' => 'Co Super',
            'username' => 'cosuper',
            'role_id' => $superRoleId,
            'password' => 'newpass1234',
            'password_confirmation' => 'newpass1234',
            'is_active' => true,
        ])
        ->assertRedirect();

    expect(User::where('username', 'cosuper')->exists())->toBeTrue();
});

test('store user otomatis set force_password_change=true', function (): void {
    $super = userControllerUser(Role::CODE_SUPERADMIN);
    $adminRoleId = Role::ofCode(Role::CODE_ADMIN)->value('id');

    $this->actingAs($super)
        ->post(route('users.store'), [
            'name' => 'Fresh',
            'username' => 'fresh01',
            'role_id' => $adminRoleId,
            'password' => 'newpass1234',
            'password_confirmation' => 'newpass1234',
        ]);

    $u = User::where('username', 'fresh01')->first();
    expect($u->force_password_change)->toBeTrue();
});

test('admin tidak bisa demote dirinya sendiri dari superadmin', function (): void {
    // skenario: admin yang ternyata superadmin coba ubah role dirinya
    $super = userControllerUser(Role::CODE_SUPERADMIN);
    $adminRoleId = Role::ofCode(Role::CODE_ADMIN)->value('id');

    $this->actingAs($super)
        ->put(route('users.update', $super->id), [
            'name' => $super->name,
            'username' => $super->username,
            'role_id' => $adminRoleId,
        ])
        ->assertSessionHasErrors('role_id');
});

test('admin tidak bisa reset password superadmin', function (): void {
    $admin = userControllerUser(Role::CODE_ADMIN);
    $super = userControllerUser(Role::CODE_SUPERADMIN);

    $this->actingAs($admin)
        ->post(route('users.reset-password', $super->id), [
            'new_password' => 'newpass1234',
        ])
        ->assertForbidden();
});

test('reset password set force_password_change & catat log', function (): void {
    $admin = userControllerUser(Role::CODE_ADMIN);
    $target = userControllerUser(Role::CODE_KASIR, ['force_password_change' => false]);

    $this->actingAs($admin)
        ->post(route('users.reset-password', $target->id), [
            'new_password' => 'reset12345',
            'reason' => 'Lupa',
        ])
        ->assertRedirect();

    $target->refresh();
    expect($target->force_password_change)->toBeTrue();
    expect(Hash::check('reset12345', $target->password))->toBeTrue();
    expect(PasswordResetLog::where('user_id', $target->id)->count())->toBe(1);
});

test('admin tidak bisa force logout dirinya sendiri', function (): void {
    $admin = userControllerUser(Role::CODE_ADMIN);

    $this->actingAs($admin)
        ->post(route('users.force-logout', $admin->id))
        ->assertForbidden();
});

test('soft-delete user oleh superadmin', function (): void {
    $super = userControllerUser(Role::CODE_SUPERADMIN);
    $target = userControllerUser(Role::CODE_KASIR);

    $this->actingAs($super)
        ->delete(route('users.destroy', $target->id))
        ->assertRedirect(route('users.index'));

    expect(User::withTrashed()->find($target->id)->trashed())->toBeTrue();
});

test('updateMenuOverrides menyimpan grant/deny dan hapus row bernilai null saja', function (): void {
    $super = userControllerUser(Role::CODE_SUPERADMIN);
    $target = userControllerUser(Role::CODE_KASIR);

    $menuSupplier = \App\Models\Menu::where('code', 'master.supplier')->first();
    $menuInvoice = \App\Models\Menu::where('code', 'sales.invoice')->first();

    $this->actingAs($super)
        ->put(route('users.menu-overrides.update', $target->id), [
            'overrides' => [
                ['menu_id' => $menuSupplier->id, 'can_view' => true, 'can_create' => null, 'can_update' => null, 'can_delete' => null, 'can_approve' => null, 'can_export' => null, 'note' => 'grant view'],
                ['menu_id' => $menuInvoice->id, 'can_view' => null, 'can_create' => null, 'can_update' => null, 'can_delete' => null, 'can_approve' => null, 'can_export' => null, 'note' => ''],
            ],
        ])
        ->assertRedirect();

    expect(UserMenuOverride::where('user_id', $target->id)->count())->toBe(1);
    $ov = UserMenuOverride::where('user_id', $target->id)->first();
    expect($ov->menu_id)->toBe($menuSupplier->id);
    expect($ov->can_view)->toBeTrue();
});
