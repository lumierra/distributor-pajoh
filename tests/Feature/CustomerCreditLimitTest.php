<?php

use App\Models\Customer;
use App\Models\CustomerSupplierCreditLimit;
use App\Models\PriceTier;
use App\Models\ProductCategory;
use App\Models\ProductUnit;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Services\Customer\CustomerCreditLimitService;
use App\Services\Product\ProductService;
use Database\Seeders\MenuSeeder;
use Database\Seeders\ProductCategorySeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(SettingSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(MenuSeeder::class);
    $this->seed(RolePermissionSeeder::class);
    $this->seed(ProductCategorySeeder::class);
});

function cclUser(string $roleCode): User
{
    $uniq = uniqid('', true);

    return User::create([
        'name' => 'U-'.$roleCode.'-'.$uniq,
        'username' => 'u_'.$roleCode.'_'.str_replace('.', '', $uniq),
        'password' => 'secret1234',
        'role_id' => Role::ofCode($roleCode)->value('id'),
        'is_active' => true,
    ]);
}

function cclCustomer(): Customer
{
    $tier = PriceTier::query()->first() ?? PriceTier::create(['code' => 'ECERAN', 'name' => 'Eceran', 'is_active' => true, 'sort_order' => 1]);

    return Customer::create([
        'code' => 'CUST-CL-'.random_int(1000, 9999),
        'name' => 'CL Customer',
        'price_tier_id' => $tier->id,
        'is_active' => true,
        'payment_term_days' => 14,
        'credit_limit' => 0,
    ]);
}

function cclSupplierWithProduct(): array
{
    $supplier = Supplier::create([
        'code' => 'SUP-'.random_int(1000, 9999),
        'name' => 'Supplier '.random_int(1, 99),
        'is_active' => true,
        'payment_term_days' => 14,
    ]);
    $product = app(ProductService::class)->create(
        [
            'name' => 'Produk CL '.random_int(100, 999),
            'category_id' => ProductCategory::where('code', 'MIE')->value('id'),
        ],
        [['level' => ProductUnit::LEVEL_KCL, 'name' => 'Pcs', 'qty_to_base' => 1]],
    );
    SupplierProduct::create([
        'supplier_id' => $supplier->id,
        'product_id' => $product->id,
        'default_cost_price' => 5000,
        'is_primary' => true,
        'is_active' => true,
    ]);

    return ['supplier' => $supplier, 'product' => $product];
}

test('snapshot empty kalau customer belum punya limit / outstanding', function (): void {
    $customer = cclCustomer();
    $snapshot = app(CustomerCreditLimitService::class)->snapshot($customer);
    expect($snapshot)->toBe([]);
});

test('assertCanCharge bypass kalau limit tidak di-set (0)', function (): void {
    $customer = cclCustomer();
    $supplier = cclSupplierWithProduct()['supplier'];

    app(CustomerCreditLimitService::class)
        ->assertCanCharge($customer, [$supplier->id => 999_999_999]);

    expect(true)->toBeTrue();
});

test('assertCanCharge throw kalau incoming > limit', function (): void {
    $customer = cclCustomer();
    $supplier = cclSupplierWithProduct()['supplier'];

    CustomerSupplierCreditLimit::create([
        'customer_id' => $customer->id,
        'supplier_id' => $supplier->id,
        'credit_limit' => 1_000_000,
    ]);

    expect(
        fn () => app(CustomerCreditLimitService::class)
            ->assertCanCharge($customer, [$supplier->id => 2_000_000]),
    )->toThrow(ValidationException::class);
});

test('assertCanCharge pass kalau incoming ≤ limit', function (): void {
    $customer = cclCustomer();
    $supplier = cclSupplierWithProduct()['supplier'];

    CustomerSupplierCreditLimit::create([
        'customer_id' => $customer->id,
        'supplier_id' => $supplier->id,
        'credit_limit' => 1_000_000,
    ]);

    app(CustomerCreditLimitService::class)
        ->assertCanCharge($customer, [$supplier->id => 500_000]);

    expect(true)->toBeTrue();
});

test('GET credit-limits return list semua supplier active', function (): void {
    $admin = cclUser(Role::CODE_SUPERADMIN);
    $customer = cclCustomer();
    cclSupplierWithProduct();
    cclSupplierWithProduct();

    $this->actingAs($admin)
        ->getJson(route('customers.credit-limits.index', $customer->id))
        ->assertOk()
        ->assertJsonStructure(['rows' => [['supplier_id', 'supplier_name', 'credit_limit', 'outstanding', 'available']]]);
});

test('PUT credit-limits sync: upsert + delete row dengan limit 0', function (): void {
    $admin = cclUser(Role::CODE_SUPERADMIN);
    $customer = cclCustomer();
    $a = cclSupplierWithProduct()['supplier'];
    $b = cclSupplierWithProduct()['supplier'];

    $this->actingAs($admin)
        ->put(route('customers.credit-limits.sync', $customer->id), [
            'rows' => [
                ['supplier_id' => $a->id, 'credit_limit' => 4_000_000],
                ['supplier_id' => $b->id, 'credit_limit' => 2_000_000],
            ],
        ])
        ->assertRedirect();

    expect(CustomerSupplierCreditLimit::where('customer_id', $customer->id)->count())->toBe(2);

    // Update a, hapus b (limit=0)
    $this->actingAs($admin)
        ->put(route('customers.credit-limits.sync', $customer->id), [
            'rows' => [
                ['supplier_id' => $a->id, 'credit_limit' => 5_000_000],
                ['supplier_id' => $b->id, 'credit_limit' => 0],
            ],
        ])
        ->assertRedirect();

    expect(CustomerSupplierCreditLimit::where('customer_id', $customer->id)->count())->toBe(1);
    expect((float) CustomerSupplierCreditLimit::query()
        ->where('customer_id', $customer->id)
        ->where('supplier_id', $a->id)
        ->value('credit_limit'))->toBe(5_000_000.0);
});

test('kasir tidak bisa sync credit-limits', function (): void {
    $kasir = cclUser(Role::CODE_KASIR);
    $customer = cclCustomer();
    $supplier = cclSupplierWithProduct()['supplier'];

    $this->actingAs($kasir)
        ->put(route('customers.credit-limits.sync', $customer->id), [
            'rows' => [['supplier_id' => $supplier->id, 'credit_limit' => 1000]],
        ])
        ->assertForbidden();
});
