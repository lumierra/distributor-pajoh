<?php

use App\Models\Driver;
use App\Models\Role;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(\Database\Seeders\SettingSeeder::class);
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->seed(\Database\Seeders\MenuSeeder::class);
    $this->seed(\Database\Seeders\RolePermissionSeeder::class);
});

function driverCrudUser(string $roleCode): User
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

test('admin bisa list driver', function (): void {
    $admin = driverCrudUser(Role::CODE_ADMIN);

    $this->actingAs($admin)
        ->get(route('drivers.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Drivers/Index'));
});

test('kasir tidak bisa list driver', function (): void {
    $kasir = driverCrudUser(Role::CODE_KASIR);

    $this->actingAs($kasir)
        ->get(route('drivers.index'))
        ->assertForbidden();
});

test('admin bisa create driver dengan code auto-generated', function (): void {
    $admin = driverCrudUser(Role::CODE_ADMIN);

    $this->actingAs($admin)
        ->post(route('drivers.store'), [
            'name' => 'Pak Ahmad',
            'phone' => '0812-3456-7890',
            'license_no' => '1234567890',
            'license_type' => 'B1',
        ])
        ->assertRedirect();

    $driver = Driver::query()->where('name', 'Pak Ahmad')->firstOrFail();
    expect($driver->code)->toStartWith('DRV-');
});

test('admin bisa update driver', function (): void {
    $admin = driverCrudUser(Role::CODE_ADMIN);
    $driver = Driver::create(['code' => 'DRV-0100', 'name' => 'Old']);

    $this->actingAs($admin)
        ->put(route('drivers.update', $driver), [
            'name' => 'New Name',
        ])
        ->assertRedirect();

    expect($driver->fresh()->name)->toBe('New Name');
});

test('toggle active mengubah status', function (): void {
    $admin = driverCrudUser(Role::CODE_ADMIN);
    $driver = Driver::create([
        'code' => 'DRV-0101',
        'name' => 'Tog',
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->post(route('drivers.toggle-active', $driver))
        ->assertRedirect();

    expect((bool) $driver->fresh()->is_active)->toBeFalse();
});

test('set unavailable lalu kembali idle', function (): void {
    $admin = driverCrudUser(Role::CODE_ADMIN);
    $driver = Driver::create(['code' => 'DRV-0102', 'name' => 'Av', 'status' => 'idle']);

    $this->actingAs($admin)
        ->post(route('drivers.set-unavailable', $driver))
        ->assertRedirect();

    expect($driver->fresh()->status)->toBe(Driver::STATUS_UNAVAILABLE);

    $this->actingAs($admin)
        ->post(route('drivers.set-unavailable', $driver))
        ->assertRedirect();

    expect($driver->fresh()->status)->toBe(Driver::STATUS_IDLE);
});

test('admin tidak bisa delete driver', function (): void {
    $admin = driverCrudUser(Role::CODE_ADMIN);
    $driver = Driver::create(['code' => 'DRV-0103', 'name' => 'Del']);

    $this->actingAs($admin)
        ->delete(route('drivers.destroy', $driver))
        ->assertForbidden();
});

test('superadmin bisa delete driver', function (): void {
    $sa = driverCrudUser(Role::CODE_SUPERADMIN);
    $driver = Driver::create(['code' => 'DRV-0104', 'name' => 'Del2']);

    $this->actingAs($sa)
        ->delete(route('drivers.destroy', $driver))
        ->assertRedirect();

    expect(Driver::query()->find($driver->id))->toBeNull();
});

test('default_vehicle_id valid menyimpan FK', function (): void {
    $admin = driverCrudUser(Role::CODE_ADMIN);
    $vehicle = Vehicle::create([
        'code' => 'VEH-0001',
        'plate_number' => 'BL 1234 AB',
        'type' => 'truck',
    ]);

    $this->actingAs($admin)
        ->post(route('drivers.store'), [
            'name' => 'Driver With Default',
            'default_vehicle_id' => $vehicle->id,
        ])
        ->assertRedirect();

    $d = Driver::query()->where('name', 'Driver With Default')->firstOrFail();
    expect($d->default_vehicle_id)->toBe($vehicle->id);
});
