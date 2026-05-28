<?php

use App\Models\Role;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Fleet\VehicleService;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(SettingSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(MenuSeeder::class);
    $this->seed(RolePermissionSeeder::class);
});

function vehicleCrudUser(string $roleCode): User
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

test('admin bisa list vehicle', function (): void {
    $admin = vehicleCrudUser(Role::CODE_ADMIN);

    $this->actingAs($admin)
        ->get(route('vehicles.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Vehicles/Index'));
});

test('admin bisa create vehicle dengan code auto-generated', function (): void {
    $admin = vehicleCrudUser(Role::CODE_ADMIN);

    $this->actingAs($admin)
        ->post(route('vehicles.store'), [
            'plate_number' => 'BL 9195 XX',
            'type' => 'truck',
            'brand' => 'Mitsubishi',
        ])
        ->assertRedirect();

    $v = Vehicle::query()->where('plate_number', 'BL 9195 XX')->firstOrFail();
    expect($v->code)->toStartWith('VEH-');
});

test('plate number duplicate ditolak', function (): void {
    $admin = vehicleCrudUser(Role::CODE_ADMIN);
    Vehicle::create(['code' => 'VEH-0001', 'plate_number' => 'BL 1111 AA', 'type' => 'pickup']);

    $this->actingAs($admin)
        ->from(route('vehicles.index'))
        ->post(route('vehicles.store'), [
            'plate_number' => 'BL 1111 AA',
            'type' => 'pickup',
        ])
        ->assertSessionHasErrors('plate_number');
});

test('plate number format invalid ditolak', function (): void {
    $admin = vehicleCrudUser(Role::CODE_ADMIN);

    $this->actingAs($admin)
        ->from(route('vehicles.index'))
        ->post(route('vehicles.store'), [
            'plate_number' => '1234',
            'type' => 'pickup',
        ])
        ->assertSessionHasErrors('plate_number');
});

test('plate number ter-normalize uppercase & single-space', function (): void {
    expect(VehicleService::normalizePlate('bl  9195   xx'))->toBe('BL 9195 XX');
    expect(VehicleService::normalizePlate('  bl 9195 xx '))->toBe('BL 9195 XX');
});

test('admin bisa update vehicle', function (): void {
    $admin = vehicleCrudUser(Role::CODE_ADMIN);
    $v = Vehicle::create(['code' => 'VEH-0002', 'plate_number' => 'BL 2222 BB', 'type' => 'pickup']);

    $this->actingAs($admin)
        ->put(route('vehicles.update', $v), [
            'plate_number' => 'BL 3333 CC',
            'type' => 'pickup',
        ])
        ->assertRedirect();

    expect($v->fresh()->plate_number)->toBe('BL 3333 CC');
});

test('admin tidak bisa delete vehicle', function (): void {
    $admin = vehicleCrudUser(Role::CODE_ADMIN);
    $v = Vehicle::create(['code' => 'VEH-0004', 'plate_number' => 'BL 5555 EE', 'type' => 'pickup']);

    $this->actingAs($admin)
        ->delete(route('vehicles.destroy', $v))
        ->assertForbidden();
});

test('superadmin bisa delete vehicle yang tidak maintenance', function (): void {
    $sa = vehicleCrudUser(Role::CODE_SUPERADMIN);
    $v = Vehicle::create(['code' => 'VEH-0005', 'plate_number' => 'BL 6666 FF', 'type' => 'pickup']);

    $this->actingAs($sa)
        ->delete(route('vehicles.destroy', $v))
        ->assertRedirect();

    expect(Vehicle::query()->find($v->id))->toBeNull();
});
