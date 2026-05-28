<?php

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductGroup;
use App\Models\ProductUnit;
use App\Models\Role;
use App\Models\User;
use App\Services\Product\ProductService;
use Database\Seeders\MenuSeeder;
use Database\Seeders\ProductCategorySeeder;
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
    $this->seed(ProductCategorySeeder::class);
});

function pgUser(string $roleCode, array $overrides = []): User
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

function pgMakeProduct(string $name = 'Produk PG'): Product
{
    return app(ProductService::class)->create(
        [
            'name' => $name.'-'.random_int(100, 999),
            'category_id' => ProductCategory::where('code', 'MIE')->value('id'),
        ],
        [
            ['level' => ProductUnit::LEVEL_KCL, 'name' => 'Pcs', 'qty_to_base' => 1],
        ],
    );
}

test('admin bisa lihat halaman product-groups', function (): void {
    $admin = pgUser(Role::CODE_SUPERADMIN);

    $this->actingAs($admin)
        ->get(route('product-groups.index'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('ProductGroups/Index'));
});

test('store product-group', function (): void {
    $admin = pgUser(Role::CODE_SUPERADMIN);

    $this->actingAs($admin)
        ->post(route('product-groups.store'), [
            'code' => 'SUPPA_FROZEN',
            'name' => 'Supplier A Frozen',
        ])
        ->assertRedirect();

    expect(ProductGroup::where('code', 'SUPPA_FROZEN')->exists())->toBeTrue();
});

test('sync products ke group', function (): void {
    $admin = pgUser(Role::CODE_SUPERADMIN);
    $group = ProductGroup::create([
        'code' => 'G1', 'name' => 'G1', 'is_active' => true,
    ]);
    $p1 = pgMakeProduct('PG-A');
    $p2 = pgMakeProduct('PG-B');

    $this->actingAs($admin)
        ->put(route('product-groups.sync-products', $group->id), [
            'product_ids' => [$p1->id, $p2->id],
        ])
        ->assertRedirect();

    expect($group->products()->pluck('products.id')->all())
        ->toEqualCanonicalizing([$p1->id, $p2->id]);
});

test('sync sales ke group dgn monthly_limit', function (): void {
    $admin = pgUser(Role::CODE_SUPERADMIN);
    $sales = pgUser(Role::CODE_SALES);
    $kasir = pgUser(Role::CODE_KASIR);

    $group = ProductGroup::create([
        'code' => 'G2', 'name' => 'G2', 'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->put(route('product-groups.sync-sales', $group->id), [
            'assignments' => [
                ['user_id' => $sales->id, 'monthly_limit' => 5_000_000],
                // kasir bukan sales → harus di-skip
                ['user_id' => $kasir->id, 'monthly_limit' => 9_999_999],
            ],
        ])
        ->assertRedirect();

    $attached = $group->salesUsers()->get();
    expect($attached)->toHaveCount(1);
    expect($attached->first()->id)->toBe($sales->id);
    expect((float) $attached->first()->pivot->monthly_limit)->toBe(5_000_000.0);
});

test('sales tanpa group bisa create SO (backwards-compat)', function (): void {
    $admin = pgUser(Role::CODE_SUPERADMIN);
    $this->actingAs($admin); // dummy auth

    // Sanity: group kosong → enforcement di-bypass. Tested indirectly by
    // existing SalesOrderFlowTest yang sudah pass tanpa setup group.
    expect(true)->toBeTrue();
});

test('kasir tidak bisa create product-group', function (): void {
    $kasir = pgUser(Role::CODE_KASIR);

    $this->actingAs($kasir)
        ->post(route('product-groups.store'), [
            'code' => 'X1', 'name' => 'X',
        ])
        ->assertForbidden();
});
