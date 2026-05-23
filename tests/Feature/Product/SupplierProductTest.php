<?php

use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Services\Product\ProductService;
use App\Services\Supplier\SupplierService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(\Database\Seeders\SettingSeeder::class);
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->seed(\Database\Seeders\MenuSeeder::class);
    $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    $this->seed(\Database\Seeders\SupplierCategorySeeder::class);
    $this->seed(\Database\Seeders\ProductCategorySeeder::class);
    $this->seed(\Database\Seeders\PriceTierSeeder::class);

    $this->admin = User::create([
        'name' => 'Admin',
        'username' => 'admin_sp_'.uniqid('', true),
        'password' => 'secret1234',
        'role_id' => Role::ofCode(Role::CODE_ADMIN)->value('id'),
        'is_active' => true,
        'force_password_change' => false,
    ]);

    $this->product = app(ProductService::class)->create(
        ['name' => 'P-Sup', 'category_id' => ProductCategory::where('code', 'FMC')->value('id')],
        [['level' => 'KCL', 'name' => 'Pcs', 'qty_to_base' => 1]],
    );

    $this->supplierA = app(SupplierService::class)->create(['name' => 'PT Supplier A']);
    $this->supplierB = app(SupplierService::class)->create(['name' => 'PT Supplier B']);
});

test('admin bisa attach supplier ke produk', function (): void {
    $this->actingAs($this->admin)
        ->post(route('products.suppliers.store', $this->product->id), [
            'supplier_id' => $this->supplierA->id,
            'supplier_sku' => 'INDM-001',
            'default_cost_price' => 2500,
            'moq' => 100,
            'is_primary' => true,
        ])
        ->assertRedirect();

    $sp = SupplierProduct::where('product_id', $this->product->id)
        ->where('supplier_id', $this->supplierA->id)->first();
    expect($sp)->not->toBeNull();
    expect($sp->is_primary)->toBeTrue();
    expect((float) $sp->default_cost_price)->toBe(2500.0);
});

test('set is_primary=true otomatis unset primary lama', function (): void {
    $this->actingAs($this->admin)->post(route('products.suppliers.store', $this->product->id), [
        'supplier_id' => $this->supplierA->id,
        'is_primary' => true,
    ]);

    $this->actingAs($this->admin)->post(route('products.suppliers.store', $this->product->id), [
        'supplier_id' => $this->supplierB->id,
        'is_primary' => true,
    ]);

    $rows = SupplierProduct::where('product_id', $this->product->id)->get();
    expect($rows)->toHaveCount(2);
    expect($rows->where('is_primary', true)->count())->toBe(1);
    // Yang baru (supplierB) yang primary
    expect($rows->firstWhere('supplier_id', $this->supplierB->id)->is_primary)->toBeTrue();
    expect($rows->firstWhere('supplier_id', $this->supplierA->id)->is_primary)->toBeFalse();
});

test('duplicate supplier per produk ditolak', function (): void {
    $this->actingAs($this->admin)->post(route('products.suppliers.store', $this->product->id), [
        'supplier_id' => $this->supplierA->id,
    ]);

    $this->actingAs($this->admin)
        ->post(route('products.suppliers.store', $this->product->id), [
            'supplier_id' => $this->supplierA->id, // duplicate
        ])
        ->assertSessionHasErrors('supplier_id');
});

test('detach supplier dari produk', function (): void {
    $this->actingAs($this->admin)->post(route('products.suppliers.store', $this->product->id), [
        'supplier_id' => $this->supplierA->id,
    ]);
    $sp = SupplierProduct::where('product_id', $this->product->id)->first();

    $this->actingAs($this->admin)
        ->delete(route('supplier-products.destroy', $sp->id))
        ->assertRedirect();

    expect(SupplierProduct::find($sp->id))->toBeNull();
});
