<?php

use App\Models\Customer;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\Product\ProductService;
use App\Services\Purchasing\GoodsReceiptService;
use App\Services\Sales\SalesOrderService;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\SuperadminSeeder;
use Database\Seeders\UnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([
        SettingSeeder::class,
        RoleSeeder::class,
        MenuSeeder::class,
        RolePermissionSeeder::class,
        SuperadminSeeder::class,
        UnitSeeder::class,
    ]);
});

function discAdmin(): User
{
    return User::query()->whereHas('role', fn ($q) => $q->where('code', Role::CODE_SUPERADMIN))->firstOrFail();
}

/** Produk PCS base, sell 1000/unit, berstok 100. */
function makeSellableProduct(): array
{
    $admin = discAdmin();
    $supplier = Supplier::create(['code' => 'SUP-D', 'name' => 'Sup', 'is_active' => true, 'created_by' => $admin->id]);
    $pcs = Unit::query()->where('name', 'PCS')->value('id');

    $product = app(ProductService::class)->create(
        ['supplier_id' => $supplier->id, 'sku' => 'DSC-1', 'name' => 'Produk Diskon', 'is_active' => true, 'created_by' => $admin->id],
        [['unit_id' => $pcs, 'qty_to_base' => 1, 'barcode' => null]],
        [['name' => 'Reguler', 'items' => [['unit_index' => 0, 'cost_price' => 500, 'sell_price' => 1000]]]],
    )->fresh(['units']);

    $grn = app(GoodsReceiptService::class)->createDraft(
        ['supplier_id' => $supplier->id, 'received_date' => now()->toDateString()],
        [['po_item_id' => null, 'product_id' => $product->id, 'product_unit_id' => $product->units->first()->id, 'batch_code' => 'B', 'qty_reguler' => 100, 'cost_price' => 500, 'condition' => 'good']],
        $admin,
    );
    app(GoodsReceiptService::class)->submit($grn, $admin);
    app(GoodsReceiptService::class)->post($grn->refresh(), $admin);

    return ['product' => $product, 'supplier' => $supplier, 'unit' => $product->units->first()];
}

function customerFor(): Customer
{
    return Customer::create(['code' => 'C-D', 'name' => 'Toko', 'is_active' => true, 'credit_limit' => 100_000_000, 'payment_term_days' => 7, 'created_by' => discAdmin()->id]);
}

test('diskon per-item persen: net & subtotal benar', function (): void {
    $admin = discAdmin();
    ['product' => $product, 'unit' => $unit] = makeSellableProduct();

    $so = app(SalesOrderService::class)->createDraft(
        ['customer_id' => customerFor()->id, 'so_date' => now()->toDateString(), 'sales_id' => $admin->id],
        [['product_id' => $product->id, 'product_unit_id' => $unit->id, 'qty' => 10, 'discount_type' => 'percent', 'discount_value' => 10]],
        $admin,
    );

    $item = $so->items()->first();
    // 1000 − 10% = 900/unit; × 10 = 9000.
    expect((float) $item->unit_net_price)->toBe(900.0);
    expect((float) $item->line_subtotal)->toBe(9000.0);
    expect((float) $so->total)->toBe(9000.0);
});

test('diskon per-item rupiah: net & subtotal benar', function (): void {
    $admin = discAdmin();
    ['product' => $product, 'unit' => $unit] = makeSellableProduct();

    $so = app(SalesOrderService::class)->createDraft(
        ['customer_id' => customerFor()->id, 'so_date' => now()->toDateString(), 'sales_id' => $admin->id],
        [['product_id' => $product->id, 'product_unit_id' => $unit->id, 'qty' => 5, 'discount_type' => 'rp', 'discount_value' => 250]],
        $admin,
    );

    $item = $so->items()->first();
    // 1000 − 250 = 750/unit; × 5 = 3750.
    expect((float) $item->unit_net_price)->toBe(750.0);
    expect((float) $item->line_subtotal)->toBe(3750.0);
});

test('cashback tingkat SO mengurangi total', function (): void {
    $admin = discAdmin();
    ['product' => $product, 'unit' => $unit] = makeSellableProduct();

    // 10 × 1000 = 10.000; cashback 1.500 → total 8.500.
    $so = app(SalesOrderService::class)->createDraft(
        ['customer_id' => customerFor()->id, 'so_date' => now()->toDateString(), 'sales_id' => $admin->id, 'cashback' => 1500],
        [['product_id' => $product->id, 'product_unit_id' => $unit->id, 'qty' => 10]],
        $admin,
    );

    expect((float) $so->subtotal)->toBe(10000.0);
    expect((float) $so->cashback)->toBe(1500.0);
    expect((float) $so->total)->toBe(8500.0);
});

test('cashback dibatasi agar total tak negatif', function (): void {
    $admin = discAdmin();
    ['product' => $product, 'unit' => $unit] = makeSellableProduct();

    // subtotal 2000, cashback 5000 → dibatasi ke 2000, total 0.
    $so = app(SalesOrderService::class)->createDraft(
        ['customer_id' => customerFor()->id, 'so_date' => now()->toDateString(), 'sales_id' => $admin->id, 'cashback' => 5000],
        [['product_id' => $product->id, 'product_unit_id' => $unit->id, 'qty' => 2]],
        $admin,
    );

    expect((float) $so->cashback)->toBe(2000.0);
    expect((float) $so->total)->toBe(0.0);
});

test('diskon item + cashback bareng: kombinasi benar', function (): void {
    $admin = discAdmin();
    ['product' => $product, 'unit' => $unit] = makeSellableProduct();

    // 10 unit @ 1000, diskon item 20% → net 800, subtotal 8000; cashback 500 → total 7500.
    $so = app(SalesOrderService::class)->createDraft(
        ['customer_id' => customerFor()->id, 'so_date' => now()->toDateString(), 'sales_id' => $admin->id, 'cashback' => 500],
        [['product_id' => $product->id, 'product_unit_id' => $unit->id, 'qty' => 10, 'discount_type' => 'percent', 'discount_value' => 20]],
        $admin,
    );

    expect((float) $so->subtotal)->toBe(8000.0);
    expect((float) $so->total)->toBe(7500.0);
});
