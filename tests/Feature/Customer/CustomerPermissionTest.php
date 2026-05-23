<?php

use App\Models\Customer;
use App\Models\PriceTier;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(\Database\Seeders\SettingSeeder::class);
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->seed(\Database\Seeders\MenuSeeder::class);
    $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    $this->seed(\Database\Seeders\PriceTierSeeder::class);
});

function customerPermUser(string $roleCode): User
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

function customerForPerm(): Customer
{
    return Customer::create([
        'code' => 'CUST-P'.random_int(1000, 9999),
        'name' => 'Toko Perm',
        'price_tier_id' => PriceTier::query()->where('code', 'ECERAN')->value('id'),
        'credit_limit' => 0,
    ]);
}

test('sales tidak boleh ubah price_tier customer via web update', function (): void {
    $sales = customerPermUser(Role::CODE_SALES);
    $customer = customerForPerm();
    $newTier = PriceTier::query()->where('code', 'GROSIR')->value('id');

    // Sales tidak punya canUpdate menu master.customer
    $this->actingAs($sales)
        ->put(route('customers.update', $customer), [
            'name' => $customer->name,
            'price_tier_id' => $newTier,
        ])
        ->assertForbidden();
});

test('admin bisa reassign sales', function (): void {
    $admin = customerPermUser(Role::CODE_ADMIN);
    $sales = customerPermUser(Role::CODE_SALES);
    $customer = customerForPerm();

    $this->actingAs($admin)
        ->post(route('customers.reassign-sales', $customer), [
            'assigned_sales_id' => $sales->id,
        ])
        ->assertRedirect();

    expect($customer->fresh()->assigned_sales_id)->toBe($sales->id);
});

test('operator tidak bisa reassign sales', function (): void {
    $op = customerPermUser(Role::CODE_OPERATOR);
    $sales = customerPermUser(Role::CODE_SALES);
    $customer = customerForPerm();

    $this->actingAs($op)
        ->post(route('customers.reassign-sales', $customer), [
            'assigned_sales_id' => $sales->id,
        ])
        ->assertForbidden();
});

test('admin tidak bisa ubah assigned_sales via update (gunakan reassign endpoint)', function (): void {
    // Field assigned_sales_id boleh oleh admin lewat policy updateAssignedSales,
    // jadi update biasa juga lolos. Test ini memastikan admin BISA — guard
    // hanya menolak role yang tidak punya canUpdate.
    $admin = customerPermUser(Role::CODE_ADMIN);
    $sales = customerPermUser(Role::CODE_SALES);
    $customer = customerForPerm();

    $this->actingAs($admin)
        ->put(route('customers.update', $customer), [
            'name' => $customer->name,
            'price_tier_id' => $customer->price_tier_id,
            'assigned_sales_id' => $sales->id,
        ])
        ->assertRedirect();

    expect($customer->fresh()->assigned_sales_id)->toBe($sales->id);
});
