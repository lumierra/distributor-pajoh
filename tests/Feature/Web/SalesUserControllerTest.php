<?php

use App\Models\Role;
use App\Models\User;
use App\Models\UserDevice;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(SettingSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(MenuSeeder::class);
    $this->seed(RolePermissionSeeder::class);
});

function salesCtrlUser(string $roleCode, array $overrides = []): User
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

test('admin bisa lihat halaman sales-users', function (): void {
    $admin = salesCtrlUser(Role::CODE_ADMIN);

    $this->actingAs($admin)
        ->get(route('sales-users.index'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('SalesUsers/Index'));
});

test('list sales-users hanya menampilkan role sales', function (): void {
    $admin = salesCtrlUser(Role::CODE_SUPERADMIN);
    salesCtrlUser(Role::CODE_SALES, ['name' => 'Sales A', 'username' => 'sales_a']);
    salesCtrlUser(Role::CODE_ADMIN, ['name' => 'Admin Z', 'username' => 'adm_z']);

    $this->actingAs($admin)
        ->get(route('sales-users.index'))
        ->assertInertia(
            fn ($p) => $p->component('SalesUsers/Index')
                ->has('users.data', 1)
                ->where('users.data.0.username', 'sales_a'),
        );
});

test('list /users tidak menampilkan role sales', function (): void {
    $admin = salesCtrlUser(Role::CODE_SUPERADMIN);
    salesCtrlUser(Role::CODE_SALES, ['name' => 'Sales A', 'username' => 'sales_a']);

    $this->actingAs($admin)
        ->get(route('users.index'))
        ->assertInertia(function ($p): void {
            $p->component('Users/Index');
            $usersData = $p->toArray()['users']['data'] ?? [];
            foreach ($usersData as $u) {
                expect($u['role']['code'] ?? null)->not->toBe('sales');
            }
        });
});

test('store sales-users buat user role=sales otomatis', function (): void {
    $admin = salesCtrlUser(Role::CODE_SUPERADMIN);

    $this->actingAs($admin)
        ->post(route('sales-users.store'), [
            'name' => 'Sales Baru',
            'username' => 'salesbaru',
        ])
        ->assertRedirect(route('sales-users.index'));

    $u = User::where('username', 'salesbaru')->first();
    expect($u)->not->toBeNull();
    expect($u->role->code)->toBe(Role::CODE_SALES);
    expect($u->force_password_change)->toBeTrue();
    expect(Hash::check('12345678', $u->password))->toBeTrue();
});

test('store sales-users dgn device pre-register otomatis bikin UserDevice', function (): void {
    $admin = salesCtrlUser(Role::CODE_SUPERADMIN);

    $this->actingAs($admin)
        ->post(route('sales-users.store'), [
            'name' => 'Sales Dev',
            'username' => 'salesdev',
            'device_uuid' => 'UUID-AWAL-123',
            'device_name' => 'Galaxy A52',
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
        ])
        ->assertRedirect();

    $u = User::where('username', 'salesdev')->first();
    $device = UserDevice::where('user_id', $u->id)->first();

    expect($device)->not->toBeNull();
    expect($device->device_uuid)->toBe('UUID-AWAL-123');
    expect($device->device_name)->toBe('Galaxy A52');
    expect($device->mac_address)->toBe('AA:BB:CC:DD:EE:FF');
    expect($device->os)->toBe('Android');
    expect($device->status)->toBe(UserDevice::STATUS_ACTIVE);
    expect($device->registered_by)->toBe($admin->id);
});

test('store sales-users tanpa device tidak bikin UserDevice', function (): void {
    $admin = salesCtrlUser(Role::CODE_SUPERADMIN);

    $this->actingAs($admin)
        ->post(route('sales-users.store'), [
            'name' => 'Sales NoDev',
            'username' => 'salesnodev',
        ])
        ->assertRedirect();

    $u = User::where('username', 'salesnodev')->first();
    expect(UserDevice::where('user_id', $u->id)->count())->toBe(0);
});

test('store sales-users dgn device_name tanpa uuid → validation error', function (): void {
    $admin = salesCtrlUser(Role::CODE_SUPERADMIN);

    $this->actingAs($admin)
        ->post(route('sales-users.store'), [
            'name' => 'Sales BadDev',
            'username' => 'salesbaddev',
            'device_name' => 'Galaxy A52',
            // device_uuid hilang → wajib kalau device_name diisi
        ])
        ->assertSessionHasErrors('device_uuid');
});

test('kasir tidak bisa create sales-user', function (): void {
    $kasir = salesCtrlUser(Role::CODE_KASIR);

    $this->actingAs($kasir)
        ->post(route('sales-users.store'), [
            'name' => 'X',
            'username' => 'x_kasir',
        ])
        ->assertForbidden();
});
