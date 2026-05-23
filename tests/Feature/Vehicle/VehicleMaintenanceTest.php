<?php

use App\Models\Role;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(\Database\Seeders\SettingSeeder::class);
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->seed(\Database\Seeders\MenuSeeder::class);
    $this->seed(\Database\Seeders\RolePermissionSeeder::class);
});

function vehicleMaintUser(string $roleCode): User
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

test('admin bisa set maintenance', function (): void {
    $admin = vehicleMaintUser(Role::CODE_ADMIN);
    $v = Vehicle::create(['code' => 'VEH-0010', 'plate_number' => 'BL 0001 GG', 'type' => 'truck']);

    $this->actingAs($admin)
        ->post(route('vehicles.maintenance.set', $v), [
            'notes' => 'Tune-up rutin',
        ])
        ->assertRedirect();

    expect($v->fresh()->status)->toBe(Vehicle::STATUS_MAINTENANCE);
});

test('admin bisa unset maintenance', function (): void {
    $admin = vehicleMaintUser(Role::CODE_ADMIN);
    $v = Vehicle::create([
        'code' => 'VEH-0011',
        'plate_number' => 'BL 0002 HH',
        'type' => 'truck',
        'status' => Vehicle::STATUS_MAINTENANCE,
    ]);

    $this->actingAs($admin)
        ->delete(route('vehicles.maintenance.unset', $v))
        ->assertRedirect();

    expect($v->fresh()->status)->toBe(Vehicle::STATUS_IDLE);
});

test('hapus vehicle status maintenance ditolak (unset dulu)', function (): void {
    $sa = vehicleMaintUser(Role::CODE_SUPERADMIN);
    $v = Vehicle::create([
        'code' => 'VEH-0012',
        'plate_number' => 'BL 0003 II',
        'type' => 'truck',
        'status' => Vehicle::STATUS_MAINTENANCE,
    ]);

    $this->actingAs($sa)
        ->from(route('vehicles.show', $v))
        ->delete(route('vehicles.destroy', $v))
        ->assertSessionHasErrors('delete');

    expect(Vehicle::query()->find($v->id))->not->toBeNull();
});

test('canBeDeleted return blocker untuk maintenance', function (): void {
    $v = Vehicle::create([
        'code' => 'VEH-0013',
        'plate_number' => 'BL 0004 JJ',
        'type' => 'truck',
        'status' => Vehicle::STATUS_MAINTENANCE,
    ]);

    $svc = app(\App\Services\Fleet\VehicleService::class);
    expect($svc->canBeDeleted($v))->not->toBeEmpty();
});

test('service.delete throw ValidationException jika maintenance', function (): void {
    $v = Vehicle::create([
        'code' => 'VEH-0014',
        'plate_number' => 'BL 0005 KK',
        'type' => 'truck',
        'status' => Vehicle::STATUS_MAINTENANCE,
    ]);

    expect(fn () => app(\App\Services\Fleet\VehicleService::class)->delete($v))
        ->toThrow(ValidationException::class);
});

test('notInMaintenance scope mengecualikan vehicle maintenance', function (): void {
    Vehicle::create(['code' => 'VEH-A', 'plate_number' => 'BL 1 AA', 'type' => 'pickup', 'status' => Vehicle::STATUS_IDLE]);
    Vehicle::create(['code' => 'VEH-B', 'plate_number' => 'BL 2 BB', 'type' => 'pickup', 'status' => Vehicle::STATUS_MAINTENANCE]);

    $count = Vehicle::query()->notInMaintenance()->count();
    expect($count)->toBe(1);
});
