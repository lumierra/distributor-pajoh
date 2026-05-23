<?php

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductUnit;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\SupplierProduct;
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

function poUser(string $roleCode): User
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

function makeSupplierWithProduct(): array
{
    $supplier = Supplier::create([
        'code' => 'SUP-T7-'.random_int(1000, 9999),
        'name' => 'Supplier T7',
        'is_active' => true,
        'payment_term_days' => 14,
    ]);

    $product = app(ProductService::class)->create(
        [
            'name' => 'Produk T7-'.random_int(100, 999),
            'category_id' => ProductCategory::where('code', 'MIE')->value('id'),
        ],
        [
            ['level' => ProductUnit::LEVEL_KCL, 'name' => 'Pcs', 'qty_to_base' => 1],
        ],
    );

    SupplierProduct::create([
        'supplier_id' => $supplier->id,
        'product_id' => $product->id,
        'default_cost_price' => 5000,
        'is_active' => true,
    ]);

    return [$supplier, $product];
}

test('admin tidak bisa create PO (superadmin only)', function (): void {
    $admin = poUser(Role::CODE_ADMIN);

    $this->actingAs($admin)
        ->get(route('purchase-orders.create'))
        ->assertForbidden();
});

test('superadmin bisa lihat list PO', function (): void {
    $sa = poUser(Role::CODE_SUPERADMIN);

    $this->actingAs($sa)
        ->get(route('purchase-orders.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('PurchaseOrders/Index'));
});

test('superadmin bisa create PO draft dengan items', function (): void {
    $sa = poUser(Role::CODE_SUPERADMIN);
    [$supplier, $product] = makeSupplierWithProduct();
    $unit = $product->units->first();

    $this->actingAs($sa)
        ->post(route('purchase-orders.store'), [
            'supplier_id' => $supplier->id,
            'po_date' => now()->format('Y-m-d'),
            'eta_date' => now()->addDays(7)->format('Y-m-d'),
            'items' => [[
                'product_id' => $product->id,
                'product_unit_id' => $unit->id,
                'qty_ordered' => 10,
                'cost_price' => 5000,
                'discount_z1_pct' => 5,
                'discount_z2_pct' => 0,
            ]],
        ])
        ->assertRedirect();

    $po = PurchaseOrder::query()->latest()->first();
    expect($po)->not->toBeNull();
    expect($po->po_number)->toStartWith('PO-');
    expect($po->status)->toBe(PurchaseOrder::STATUS_DRAFT);
    expect((float) $po->subtotal)->toBe(47500.00); // 10 × (5000 × 0.95) = 47500
    expect((float) $po->total)->toBe(47500.00);
});

test('create PO dengan produk tidak di supplier_products ditolak', function (): void {
    $sa = poUser(Role::CODE_SUPERADMIN);
    [$supplier, $product] = makeSupplierWithProduct();

    // Buat produk lain yang tidak ditautkan
    $orphan = app(ProductService::class)->create(
        ['name' => 'Orphan', 'category_id' => ProductCategory::where('code', 'MIE')->value('id')],
        [['level' => 'KCL', 'name' => 'Pcs', 'qty_to_base' => 1]],
    );

    $this->actingAs($sa)
        ->from(route('purchase-orders.create'))
        ->post(route('purchase-orders.store'), [
            'supplier_id' => $supplier->id,
            'po_date' => now()->format('Y-m-d'),
            'items' => [[
                'product_id' => $orphan->id,
                'product_unit_id' => $orphan->units->first()->id,
                'qty_ordered' => 1,
                'cost_price' => 1000,
            ]],
        ])
        ->assertSessionHasErrors('items.0.product_id');
});

test('subtotal compound Z1+Z2 dihitung benar', function (): void {
    $sa = poUser(Role::CODE_SUPERADMIN);
    [$supplier, $product] = makeSupplierWithProduct();

    $this->actingAs($sa)->post(route('purchase-orders.store'), [
        'supplier_id' => $supplier->id,
        'po_date' => now()->format('Y-m-d'),
        'items' => [[
            'product_id' => $product->id,
            'product_unit_id' => $product->units->first()->id,
            'qty_ordered' => 100,
            'cost_price' => 10000,
            'discount_z1_pct' => 10,
            'discount_z2_pct' => 5,
        ]],
    ]);

    $po = PurchaseOrder::query()->latest()->first();
    // 10000 × 0.9 × 0.95 = 8550 ; × 100 = 855000
    expect((float) $po->total)->toBe(855_000.00);
});

test('bonus_qty tidak ter-hitung di subtotal', function (): void {
    $sa = poUser(Role::CODE_SUPERADMIN);
    [$supplier, $product] = makeSupplierWithProduct();

    $this->actingAs($sa)->post(route('purchase-orders.store'), [
        'supplier_id' => $supplier->id,
        'po_date' => now()->format('Y-m-d'),
        'items' => [[
            'product_id' => $product->id,
            'product_unit_id' => $product->units->first()->id,
            'qty_ordered' => 10,
            'bonus_qty' => 5,
            'cost_price' => 1000,
        ]],
    ]);

    $po = PurchaseOrder::query()->latest()->first();
    expect((float) $po->total)->toBe(10_000.00); // 10 × 1000, bonus excluded
    expect($po->items->first()->bonus_qty)->toBe(5);
});

test('header discount percent diterapkan', function (): void {
    $sa = poUser(Role::CODE_SUPERADMIN);
    [$supplier, $product] = makeSupplierWithProduct();

    $this->actingAs($sa)->post(route('purchase-orders.store'), [
        'supplier_id' => $supplier->id,
        'po_date' => now()->format('Y-m-d'),
        'header_discount_type' => 'percent',
        'header_discount_value' => 10,
        'items' => [[
            'product_id' => $product->id,
            'product_unit_id' => $product->units->first()->id,
            'qty_ordered' => 10,
            'cost_price' => 1000,
        ]],
    ]);

    $po = PurchaseOrder::query()->latest()->first();
    expect((float) $po->subtotal)->toBe(10_000.00);
    expect((float) $po->header_discount_amount)->toBe(1_000.00);
    expect((float) $po->total)->toBe(9_000.00);
});

test('po_number auto-generated dengan format PO-YYMM-...', function (): void {
    $sa = poUser(Role::CODE_SUPERADMIN);
    [$supplier, $product] = makeSupplierWithProduct();

    $this->actingAs($sa)->post(route('purchase-orders.store'), [
        'supplier_id' => $supplier->id,
        'po_date' => now()->format('Y-m-d'),
        'items' => [[
            'product_id' => $product->id,
            'product_unit_id' => $product->units->first()->id,
            'qty_ordered' => 1,
            'cost_price' => 1000,
        ]],
    ]);

    $po = PurchaseOrder::query()->latest()->first();
    $expectedPrefix = 'PO-'.now()->format('y').now()->format('m').'-';
    expect($po->po_number)->toStartWith($expectedPrefix);
});

test('fiscal_year ter-isi dari po_date', function (): void {
    $sa = poUser(Role::CODE_SUPERADMIN);
    [$supplier, $product] = makeSupplierWithProduct();

    $this->actingAs($sa)->post(route('purchase-orders.store'), [
        'supplier_id' => $supplier->id,
        'po_date' => '2026-07-15',
        'items' => [[
            'product_id' => $product->id,
            'product_unit_id' => $product->units->first()->id,
            'qty_ordered' => 1,
            'cost_price' => 1000,
        ]],
    ]);

    $po = PurchaseOrder::query()->latest()->first();
    expect($po->fiscal_year)->toBe(2026);
});
