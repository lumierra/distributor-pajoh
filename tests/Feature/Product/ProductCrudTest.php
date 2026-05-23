<?php

use App\Models\PriceTier;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductPrice;
use App\Models\ProductUnit;
use App\Models\Role;
use App\Models\User;
use App\Services\Product\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(\Database\Seeders\SettingSeeder::class);
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->seed(\Database\Seeders\MenuSeeder::class);
    $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    $this->seed(\Database\Seeders\ProductCategorySeeder::class);
    $this->seed(\Database\Seeders\PriceTierSeeder::class);
});

function productCrudUser(string $roleCode): User
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

test('admin bisa list produk', function (): void {
    $admin = productCrudUser(Role::CODE_ADMIN);

    $this->actingAs($admin)
        ->get(route('products.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Products/Index'));
});

test('kasir tidak bisa list produk', function (): void {
    $kasir = productCrudUser(Role::CODE_KASIR);

    $this->actingAs($kasir)
        ->get(route('products.index'))
        ->assertForbidden();
});

test('create produk dengan KCL saja sukses, SKU auto-generate, price matrix dibuat', function (): void {
    $admin = productCrudUser(Role::CODE_ADMIN);
    $catId = ProductCategory::where('code', 'FMC')->value('id');

    $this->actingAs($admin)
        ->post(route('products.store'), [
            'name' => 'Indomie Goreng',
            'category_id' => $catId,
            'units' => [
                ['level' => ProductUnit::LEVEL_KCL, 'name' => 'Pcs', 'qty_to_base' => 1],
            ],
        ])
        ->assertRedirect();

    $product = Product::where('name', 'Indomie Goreng')->first();
    expect($product)->not->toBeNull();
    expect($product->sku)->toMatch('/^SKU-FMC-\d{4}$/');
    expect($product->base_unit_id)->not->toBeNull();
    expect($product->units)->toHaveCount(1);

    // Price matrix: 1 unit × 3 tier = 3 row, semua 0
    $count = ProductPrice::where('product_id', $product->id)->count();
    expect($count)->toBe(3);
    expect(ProductPrice::where('product_id', $product->id)->where('price', 0)->count())->toBe(3);
});

test('create produk dengan 3 unit (BSR, TGH, KCL) generate 9 price row', function (): void {
    $admin = productCrudUser(Role::CODE_ADMIN);
    $catId = ProductCategory::where('code', 'MIE')->value('id');

    $this->actingAs($admin)
        ->post(route('products.store'), [
            'name' => 'Indomie Karton',
            'category_id' => $catId,
            'units' => [
                ['level' => ProductUnit::LEVEL_BSR, 'name' => 'Karton', 'qty_to_base' => 40],
                ['level' => ProductUnit::LEVEL_TGH, 'name' => 'Pak', 'qty_to_base' => 10],
                ['level' => ProductUnit::LEVEL_KCL, 'name' => 'Pcs', 'qty_to_base' => 1],
            ],
        ])
        ->assertRedirect();

    $product = Product::where('name', 'Indomie Karton')->first();
    expect($product->units)->toHaveCount(3);
    expect(ProductPrice::where('product_id', $product->id)->count())->toBe(9); // 3 units × 3 tiers
});

test('create produk tanpa KCL ditolak', function (): void {
    $admin = productCrudUser(Role::CODE_ADMIN);

    $this->actingAs($admin)
        ->post(route('products.store'), [
            'name' => 'No KCL',
            'units' => [
                ['level' => ProductUnit::LEVEL_BSR, 'name' => 'Krt', 'qty_to_base' => 40],
            ],
        ])
        ->assertSessionHasErrors('units');
});

test('create produk dengan UoM hierarchy invalid (BSR ≤ TGH) ditolak', function (): void {
    $admin = productCrudUser(Role::CODE_ADMIN);

    $this->actingAs($admin)
        ->post(route('products.store'), [
            'name' => 'Bad UoM',
            'units' => [
                ['level' => ProductUnit::LEVEL_BSR, 'name' => 'Krt', 'qty_to_base' => 10],
                ['level' => ProductUnit::LEVEL_TGH, 'name' => 'Pak', 'qty_to_base' => 10],
                ['level' => ProductUnit::LEVEL_KCL, 'name' => 'Pcs', 'qty_to_base' => 1],
            ],
        ])
        ->assertStatus(500); // InvalidArgumentException dari UomConverter::validateHierarchy
});

test('update produk: SKU read-only, lain bisa diubah', function (): void {
    $admin = productCrudUser(Role::CODE_ADMIN);
    $product = app(ProductService::class)->create(
        ['name' => 'P1', 'category_id' => ProductCategory::where('code', 'FMC')->value('id')],
        [['level' => 'KCL', 'name' => 'Pcs', 'qty_to_base' => 1]],
    );
    $originalSku = $product->sku;

    $this->actingAs($admin)
        ->put(route('products.update', $product->id), [
            'name' => 'P1 Updated',
            'sku' => 'HACK-001', // harus diabaikan
            'category_id' => $product->category_id,
        ])
        ->assertRedirect();

    $product->refresh();
    expect($product->name)->toBe('P1 Updated');
    expect($product->sku)->toBe($originalSku);
});

test('toggle active mengubah is_active', function (): void {
    $admin = productCrudUser(Role::CODE_ADMIN);
    $product = app(ProductService::class)->create(
        ['name' => 'P-Toggle', 'category_id' => ProductCategory::where('code', 'FMC')->value('id')],
        [['level' => 'KCL', 'name' => 'Pcs', 'qty_to_base' => 1]],
    );

    expect($product->is_active)->toBeTrue();

    $this->actingAs($admin)
        ->post(route('products.toggle-active', $product->id))
        ->assertRedirect();

    expect($product->fresh()->is_active)->toBeFalse();
});

test('superadmin bisa delete produk, admin tidak (sesuai matrix)', function (): void {
    $super = productCrudUser(Role::CODE_SUPERADMIN);
    $admin = productCrudUser(Role::CODE_ADMIN);
    $product = app(ProductService::class)->create(
        ['name' => 'P-Del', 'category_id' => ProductCategory::where('code', 'FMC')->value('id')],
        [['level' => 'KCL', 'name' => 'Pcs', 'qty_to_base' => 1]],
    );

    $this->actingAs($admin)
        ->delete(route('products.destroy', $product->id))
        ->assertForbidden();

    $this->actingAs($super)
        ->delete(route('products.destroy', $product->id))
        ->assertRedirect();

    expect(Product::withTrashed()->find($product->id)->trashed())->toBeTrue();
});

test('show menampilkan produk dengan unit, price, supplier preload', function (): void {
    $admin = productCrudUser(Role::CODE_ADMIN);
    $product = app(ProductService::class)->create(
        ['name' => 'P-Show', 'category_id' => ProductCategory::where('code', 'FMC')->value('id')],
        [['level' => 'KCL', 'name' => 'Pcs', 'qty_to_base' => 1]],
    );

    $this->actingAs($admin)
        ->get(route('products.show', $product->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Products/Show')
            ->where('product.id', $product->id)
            ->has('product.units')
            ->has('product.prices'));
});
