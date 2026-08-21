<?php

use App\Models\Customer;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\SuperadminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([
        SettingSeeder::class,
        RoleSeeder::class,
        MenuSeeder::class,
        RolePermissionSeeder::class,
        SuperadminSeeder::class,
    ]);
});

function actingSuperadmin(): User
{
    return User::query()->whereHas('role', fn ($q) => $q->where('code', Role::CODE_SUPERADMIN))->firstOrFail();
}

function makeSales(): User
{
    return User::factory()->create([
        'role_id' => Role::query()->where('code', Role::CODE_SALES)->value('id'),
        'is_active' => true,
    ]);
}

test('sales user without any linked data can be deleted', function (): void {
    $superadmin = actingSuperadmin();
    $sales = makeSales();

    $response = $this->actingAs($superadmin)->delete(route('sales-users.destroy', $sales));

    $response->assertRedirect(route('sales-users.index'));
    $this->assertSoftDeleted('users', ['id' => $sales->id]);
});

test('sales user with a sales order cannot be deleted and gets an explanatory error', function (): void {
    $superadmin = actingSuperadmin();
    $sales = makeSales();

    $customer = Customer::create([
        'code' => 'CUST-001',
        'name' => 'Toko Test',
        'is_active' => true,
    ]);

    SalesOrder::create([
        'so_number' => 'SO-TEST-001',
        'customer_id' => $customer->id,
        'sales_id' => $sales->id,
        'so_date' => now()->toDateString(),
        'status' => 'draft',
        'fiscal_year' => (int) now()->format('Y'),
    ]);

    $response = $this->actingAs($superadmin)->delete(route('sales-users.destroy', $sales));

    $response->assertRedirect();
    $response->assertSessionHas('flash.error');
    expect(session('flash.error'))->toContain('Sales Order');
    $this->assertDatabaseHas('users', ['id' => $sales->id, 'deleted_at' => null]);
});

test('sales user with an assigned customer cannot be deleted', function (): void {
    $superadmin = actingSuperadmin();
    $sales = makeSales();

    Customer::create([
        'code' => 'CUST-002',
        'name' => 'Toko Assign',
        'is_active' => true,
        'assigned_sales_id' => $sales->id,
    ]);

    $response = $this->actingAs($superadmin)->delete(route('sales-users.destroy', $sales));

    $response->assertSessionHas('flash.error');
    expect(session('flash.error'))->toContain('Customer yang di-assign');
    $this->assertDatabaseHas('users', ['id' => $sales->id, 'deleted_at' => null]);
});
