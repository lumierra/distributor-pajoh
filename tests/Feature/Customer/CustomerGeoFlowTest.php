<?php

use App\Models\Customer;
use App\Models\CustomerGeoPending;
use App\Models\PriceTier;
use App\Models\Role;
use App\Models\User;
use App\Services\Customer\CustomerGeoService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(\Database\Seeders\SettingSeeder::class);
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->seed(\Database\Seeders\MenuSeeder::class);
    $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    $this->seed(\Database\Seeders\PriceTierSeeder::class);

    $this->geo = app(CustomerGeoService::class);
});

function geoFlowUser(string $roleCode): User
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

function makeCustomerForGeo(): Customer
{
    return Customer::create([
        'code' => 'CUST-G'.random_int(1000, 9999),
        'name' => 'Toko Geo',
        'price_tier_id' => PriceTier::query()->where('code', 'ECERAN')->value('id'),
    ]);
}

test('admin set manual koordinat', function (): void {
    $admin = geoFlowUser(Role::CODE_ADMIN);
    $customer = makeCustomerForGeo();

    $this->actingAs($admin)
        ->put(route('customers.geo.update', $customer), [
            'latitude' => 4.4683,
            'longitude' => 97.9740,
        ])
        ->assertRedirect();

    $customer->refresh();
    expect((float) $customer->latitude)->toBe(4.4683);
    expect((float) $customer->longitude)->toBe(97.9740);
    expect($customer->geo_confirmed_at)->not->toBeNull();
});

test('service capturePending insert pending row', function (): void {
    $sales = geoFlowUser(Role::CODE_SALES);
    $customer = makeCustomerForGeo();

    $pending = $this->geo->capturePending($customer, 4.50, 97.98, $sales);

    expect($pending->status)->toBe(CustomerGeoPending::STATUS_PENDING);
    expect((float) $pending->captured_latitude)->toBe(4.50);
});

test('approve pending update customer & mark approved', function (): void {
    $admin = geoFlowUser(Role::CODE_ADMIN);
    $sales = geoFlowUser(Role::CODE_SALES);
    $customer = makeCustomerForGeo();

    $pending = $this->geo->capturePending($customer, 4.55, 97.95, $sales);

    $this->actingAs($admin)
        ->post(route('customers.geo-pending.approve', [$customer, $pending]))
        ->assertRedirect();

    $customer->refresh();
    $pending->refresh();
    expect((float) $customer->latitude)->toBe(4.55);
    expect($pending->status)->toBe(CustomerGeoPending::STATUS_APPROVED);
});

test('approve auto-reject sibling pending', function (): void {
    $admin = geoFlowUser(Role::CODE_ADMIN);
    $sales1 = geoFlowUser(Role::CODE_SALES);
    $sales2 = geoFlowUser(Role::CODE_SALES);
    $customer = makeCustomerForGeo();

    $p1 = $this->geo->capturePending($customer, 4.50, 97.90, $sales1);
    $p2 = $this->geo->capturePending($customer, 4.51, 97.91, $sales2);

    $this->actingAs($admin)
        ->post(route('customers.geo-pending.approve', [$customer, $p1]))
        ->assertRedirect();

    expect($p2->fresh()->status)->toBe(CustomerGeoPending::STATUS_REJECTED);
});

test('reject pending dengan reason', function (): void {
    $admin = geoFlowUser(Role::CODE_ADMIN);
    $sales = geoFlowUser(Role::CODE_SALES);
    $customer = makeCustomerForGeo();

    $pending = $this->geo->capturePending($customer, 4.50, 97.90, $sales);

    $this->actingAs($admin)
        ->post(route('customers.geo-pending.reject', [$customer, $pending]), [
            'reason' => 'GPS tidak akurat',
        ])
        ->assertRedirect();

    $pending->refresh();
    expect($pending->status)->toBe(CustomerGeoPending::STATUS_REJECTED);
    expect($pending->rejection_reason)->toBe('GPS tidak akurat');
    // Customer coords tidak berubah
    expect($customer->fresh()->latitude)->toBeNull();
});

test('haversine distance kira-kira benar', function (): void {
    $sales = geoFlowUser(Role::CODE_SALES);
    $customer = makeCustomerForGeo();
    $this->geo->setManual($customer, 4.4683, 97.9740, $sales);

    // Posisi ~111 meter ke utara (0.001° lat ≈ 111m)
    $dist = $this->geo->distanceMeter($customer->fresh(), 4.4693, 97.9740);

    expect($dist)->toBeGreaterThan(100)->toBeLessThan(120);
});
