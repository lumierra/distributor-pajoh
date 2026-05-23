<?php

use App\Models\Customer;
use App\Models\CustomerType;
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
    $this->seed(\Database\Seeders\ProductCategorySeeder::class);
    $this->seed(\Database\Seeders\PriceTierSeeder::class);
    $this->seed(\Database\Seeders\CustomerTypeSeeder::class);
});

function customerCrudUser(string $roleCode): User
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

function defaultTierId(): int
{
    return PriceTier::query()->where('code', 'ECERAN')->value('id');
}

test('admin bisa list customer', function (): void {
    $admin = customerCrudUser(Role::CODE_ADMIN);

    $this->actingAs($admin)
        ->get(route('customers.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Customers/Index'));
});

test('kasir tidak bisa list customer (default no view)', function (): void {
    $kasir = customerCrudUser(Role::CODE_KASIR);

    $this->actingAs($kasir)
        ->get(route('customers.index'))
        ->assertForbidden();
});

test('admin bisa create customer dengan code auto-generated', function (): void {
    $admin = customerCrudUser(Role::CODE_ADMIN);

    $this->actingAs($admin)
        ->post(route('customers.store'), [
            'name' => 'Toko Wani',
            'price_tier_id' => defaultTierId(),
            'customer_type_id' => CustomerType::where('code', 'WARUNG')->value('id'),
            'phone' => '0812-3456-7890',
            'address' => 'Jl. Merdeka No. 1',
            'city' => 'Langsa',
        ])
        ->assertRedirect();

    $customer = Customer::query()->where('name', 'Toko Wani')->firstOrFail();
    expect($customer->code)->toStartWith('CUST-');
});

test('create customer tanpa price_tier ditolak', function (): void {
    $admin = customerCrudUser(Role::CODE_ADMIN);

    $this->actingAs($admin)
        ->from(route('customers.index'))
        ->post(route('customers.store'), [
            'name' => 'Toko Tanpa Tier',
        ])
        ->assertSessionHasErrors('price_tier_id');
});

test('latitude tanpa longitude ditolak', function (): void {
    $admin = customerCrudUser(Role::CODE_ADMIN);

    $this->actingAs($admin)
        ->from(route('customers.index'))
        ->post(route('customers.store'), [
            'name' => 'Toko Geo Setengah',
            'price_tier_id' => defaultTierId(),
            'latitude' => 4.4683,
        ])
        ->assertSessionHasErrors('longitude');
});

test('show menampilkan customer detail', function (): void {
    $admin = customerCrudUser(Role::CODE_ADMIN);

    $customer = Customer::create([
        'code' => 'CUST-0099',
        'name' => 'Toko Test',
        'price_tier_id' => defaultTierId(),
    ]);

    $this->actingAs($admin)
        ->get(route('customers.show', $customer))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Customers/Show')
            ->where('customer.id', $customer->id),
        );
});

test('admin bisa update customer', function (): void {
    $admin = customerCrudUser(Role::CODE_ADMIN);
    $customer = Customer::create([
        'code' => 'CUST-0100',
        'name' => 'Old Name',
        'price_tier_id' => defaultTierId(),
    ]);

    $this->actingAs($admin)
        ->put(route('customers.update', $customer), [
            'name' => 'New Name',
            'price_tier_id' => $customer->price_tier_id,
        ])
        ->assertRedirect();

    expect($customer->fresh()->name)->toBe('New Name');
});

test('admin tidak bisa ubah credit_limit (superadmin only)', function (): void {
    $admin = customerCrudUser(Role::CODE_ADMIN);
    $customer = Customer::create([
        'code' => 'CUST-0101',
        'name' => 'Toko CL',
        'price_tier_id' => defaultTierId(),
        'credit_limit' => 0,
    ]);

    $this->actingAs($admin)
        ->put(route('customers.update', $customer), [
            'name' => $customer->name,
            'price_tier_id' => $customer->price_tier_id,
            'credit_limit' => 1_000_000,
        ])
        ->assertForbidden();

    expect((float) $customer->fresh()->credit_limit)->toBe(0.0);
});

test('superadmin bisa ubah credit_limit', function (): void {
    $sa = customerCrudUser(Role::CODE_SUPERADMIN);
    $customer = Customer::create([
        'code' => 'CUST-0102',
        'name' => 'Toko CL2',
        'price_tier_id' => defaultTierId(),
    ]);

    $this->actingAs($sa)
        ->put(route('customers.update', $customer), [
            'name' => $customer->name,
            'price_tier_id' => $customer->price_tier_id,
            'credit_limit' => 5_000_000,
        ])
        ->assertRedirect();

    expect((float) $customer->fresh()->credit_limit)->toBe(5_000_000.0);
});

test('admin tidak bisa delete customer', function (): void {
    $admin = customerCrudUser(Role::CODE_ADMIN);
    $customer = Customer::create([
        'code' => 'CUST-0103',
        'name' => 'No Delete',
        'price_tier_id' => defaultTierId(),
    ]);

    $this->actingAs($admin)
        ->delete(route('customers.destroy', $customer))
        ->assertForbidden();
});

test('superadmin bisa delete customer', function (): void {
    $sa = customerCrudUser(Role::CODE_SUPERADMIN);
    $customer = Customer::create([
        'code' => 'CUST-0104',
        'name' => 'Delete OK',
        'price_tier_id' => defaultTierId(),
    ]);

    $this->actingAs($sa)
        ->delete(route('customers.destroy', $customer))
        ->assertRedirect();

    expect(Customer::query()->find($customer->id))->toBeNull();
});

test('toggle active mengubah status', function (): void {
    $admin = customerCrudUser(Role::CODE_ADMIN);
    $customer = Customer::create([
        'code' => 'CUST-0105',
        'name' => 'Toggle',
        'price_tier_id' => defaultTierId(),
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->post(route('customers.toggle-active', $customer))
        ->assertRedirect();

    expect((bool) $customer->fresh()->is_active)->toBeFalse();
});
