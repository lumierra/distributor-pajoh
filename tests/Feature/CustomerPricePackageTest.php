<?php

use App\Models\Customer;
use App\Models\CustomerProductPricePackage;
use App\Models\Product;
use App\Models\ProductPricePackage;
use App\Models\ProductPricePackageItem;
use App\Models\ProductUnit;
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

function ppAdmin(): User
{
    return User::query()->whereHas('role', fn ($q) => $q->where('code', Role::CODE_SUPERADMIN))->firstOrFail();
}

/**
 * Bikin produk (PCS base, KARDUS x40) dgn 1 paket "Harga Reguler" (PCS sellPcs),
 * terima stok 500 PCS via GRN. SKU & supplier code unik per $tag.
 *
 * @return array{product: Product, pcsUnit: ProductUnit}
 */
function ppSeedProduct(string $tag, float $sellPcs = 3000): array
{
    $admin = ppAdmin();

    $supplier = Supplier::create([
        'code' => "SUP-{$tag}",
        'name' => "Supplier {$tag}",
        'is_active' => true,
        'created_by' => $admin->id,
    ]);

    $pcs = Unit::query()->where('name', 'PCS')->value('id');
    $kardus = Unit::query()->where('name', 'KARDUS')->value('id');

    $product = app(ProductService::class)->create(
        [
            'supplier_id' => $supplier->id,
            'sku' => "PP-{$tag}",
            'name' => "Produk {$tag}",
            'is_active' => true,
            'created_by' => $admin->id,
        ],
        [
            ['unit_id' => $pcs, 'qty_to_base' => 1, 'barcode' => null],
            ['unit_id' => $kardus, 'qty_to_base' => 40, 'barcode' => null],
        ],
        [[
            'name' => 'Harga Reguler',
            'items' => [
                ['unit_index' => 0, 'cost_price' => 2000, 'sell_price' => $sellPcs],
                ['unit_index' => 1, 'cost_price' => 80000, 'sell_price' => 120000],
            ],
        ]],
    )->fresh(['units']);

    $pcsUnit = $product->units->firstWhere('qty_to_base', 1);

    $grn = app(GoodsReceiptService::class)->createDraft(
        ['supplier_id' => $supplier->id, 'received_date' => now()->toDateString()],
        [[
            'po_item_id' => null,
            'product_id' => $product->id,
            'product_unit_id' => $pcsUnit->id,
            'batch_code' => "BATCH-{$tag}",
            'qty_reguler' => 500,
            'cost_price' => 2000,
            'condition' => 'good',
        ]],
        $admin,
    );
    $svc = app(GoodsReceiptService::class);
    $svc->submit($grn, $admin);
    $svc->post($grn->refresh(), $admin);

    return ['product' => $product, 'pcsUnit' => $pcsUnit];
}

function ppMakeCustomer(): Customer
{
    return Customer::create([
        'code' => 'CUST-PP',
        'name' => 'Toko PP',
        'is_active' => true,
        'credit_limit' => 100_000_000,
        'payment_term_days' => 30,
        'created_by' => ppAdmin()->id,
    ]);
}

/** Tambah paket kedua "Harga Grosir" (PCS = $sellPcs) ke produk. */
function ppAddPackage($product, float $sellPcs): ProductPricePackage
{
    $pcsUnit = $product->fresh(['units'])->units->firstWhere('qty_to_base', 1);

    $pkg = ProductPricePackage::create([
        'product_id' => $product->id,
        'name' => 'Harga Grosir',
        'sort_order' => 1,
        'is_active' => true,
        'created_by' => ppAdmin()->id,
    ]);

    ProductPricePackageItem::create([
        'price_package_id' => $pkg->id,
        'product_unit_id' => $pcsUnit->id,
        'cost_price' => 2000,
        'sell_price' => $sellPcs,
    ]);

    return $pkg;
}

test('SO pakai harga default (paket pertama) bila customer tidak di-set', function (): void {
    $admin = ppAdmin();
    ['product' => $product, 'pcsUnit' => $pcsUnit] = ppSeedProduct('A');
    $customer = ppMakeCustomer();
    ppAddPackage($product, 5000); // paket kedua ada tapi tak di-assign

    $so = app(SalesOrderService::class)->createDraft(
        ['customer_id' => $customer->id, 'so_date' => now()->toDateString(), 'sales_id' => $admin->id],
        [['product_id' => $product->id, 'product_unit_id' => $pcsUnit->id, 'qty' => 10]],
        $admin,
    );

    expect((float) $so->items()->first()->unit_price)->toBe(3000.0);
});

test('SO pakai paket harga yang di-assign ke customer untuk produk itu', function (): void {
    $admin = ppAdmin();
    ['product' => $product, 'pcsUnit' => $pcsUnit] = ppSeedProduct('A');
    $customer = ppMakeCustomer();
    $grosir = ppAddPackage($product, 5000);

    CustomerProductPricePackage::create([
        'customer_id' => $customer->id,
        'product_id' => $product->id,
        'price_package_id' => $grosir->id,
        'created_by' => $admin->id,
    ]);

    $so = app(SalesOrderService::class)->createDraft(
        ['customer_id' => $customer->id, 'so_date' => now()->toDateString(), 'sales_id' => $admin->id],
        [['product_id' => $product->id, 'product_unit_id' => $pcsUnit->id, 'qty' => 10]],
        $admin,
    );

    expect((float) $so->items()->first()->unit_price)->toBe(5000.0);
    expect((float) $so->total)->toBe(50000.0);
});

test('assignment paket customer produk lain tidak mempengaruhi produk ini', function (): void {
    $admin = ppAdmin();
    ['product' => $product, 'pcsUnit' => $pcsUnit] = ppSeedProduct('A');
    ['product' => $other] = ppSeedProduct('B');
    $customer = ppMakeCustomer();
    $otherGrosir = ppAddPackage($other, 9000);

    // Assign paket produk B ke customer — produk A tak punya assignment.
    CustomerProductPricePackage::create([
        'customer_id' => $customer->id,
        'product_id' => $other->id,
        'price_package_id' => $otherGrosir->id,
        'created_by' => $admin->id,
    ]);

    $so = app(SalesOrderService::class)->createDraft(
        ['customer_id' => $customer->id, 'so_date' => now()->toDateString(), 'sales_id' => $admin->id],
        [['product_id' => $product->id, 'product_unit_id' => $pcsUnit->id, 'qty' => 10]],
        $admin,
    );

    expect((float) $so->items()->first()->unit_price)->toBe(3000.0);
});

test('endpoint sync menyimpan lalu menghapus assignment paket harga', function (): void {
    $admin = ppAdmin();
    ['product' => $product] = ppSeedProduct('A');
    $customer = ppMakeCustomer();
    $grosir = ppAddPackage($product, 5000);

    $this->actingAs($admin)
        ->put(route('customers.price-packages.sync', $customer->id), [
            'rows' => [['product_id' => $product->id, 'price_package_id' => $grosir->id]],
        ])
        ->assertRedirect();

    expect(CustomerProductPricePackage::query()
        ->where('customer_id', $customer->id)
        ->where('product_id', $product->id)
        ->value('price_package_id'))->toBe($grosir->id);

    $this->actingAs($admin)
        ->put(route('customers.price-packages.sync', $customer->id), [
            'rows' => [['product_id' => $product->id, 'price_package_id' => null]],
        ])
        ->assertRedirect();

    expect(CustomerProductPricePackage::query()
        ->where('customer_id', $customer->id)
        ->where('product_id', $product->id)
        ->exists())->toBeFalse();
});

test('sync menghapus assignment produk yang tidak ada di daftar (reconcile)', function (): void {
    $admin = ppAdmin();
    ['product' => $productA] = ppSeedProduct('A');
    ['product' => $productB] = ppSeedProduct('B');
    $customer = ppMakeCustomer();
    $grosirA = ppAddPackage($productA, 5000);
    $grosirB = ppAddPackage($productB, 7000);

    // Set dua produk.
    $this->actingAs($admin)
        ->put(route('customers.price-packages.sync', $customer->id), [
            'rows' => [
                ['product_id' => $productA->id, 'price_package_id' => $grosirA->id],
                ['product_id' => $productB->id, 'price_package_id' => $grosirB->id],
            ],
        ])
        ->assertRedirect();

    expect(CustomerProductPricePackage::where('customer_id', $customer->id)->count())->toBe(2);

    // Kirim hanya produk A → assignment B harus terhapus.
    $this->actingAs($admin)
        ->put(route('customers.price-packages.sync', $customer->id), [
            'rows' => [
                ['product_id' => $productA->id, 'price_package_id' => $grosirA->id],
            ],
        ])
        ->assertRedirect();

    expect(CustomerProductPricePackage::where('customer_id', $customer->id)->count())->toBe(1);
    expect(CustomerProductPricePackage::query()
        ->where('customer_id', $customer->id)
        ->where('product_id', $productB->id)
        ->exists())->toBeFalse();

    // Kirim daftar kosong → semua assignment terhapus.
    $this->actingAs($admin)
        ->put(route('customers.price-packages.sync', $customer->id), ['rows' => []])
        ->assertRedirect();

    expect(CustomerProductPricePackage::where('customer_id', $customer->id)->count())->toBe(0);
});

test('endpoint product-catalog mengembalikan harga sesuai paket customer', function (): void {
    $admin = ppAdmin();
    ['product' => $product, 'pcsUnit' => $pcsUnit] = ppSeedProduct('A');
    $customer = ppMakeCustomer();
    $grosir = ppAddPackage($product, 5000);

    // Tanpa customer_id → harga default paket pertama (PCS = 3000).
    $before = $this->actingAs($admin)
        ->getJson(route('sales-orders.product-catalog'))
        ->assertOk()
        ->json('products');
    $prodBefore = collect($before)->firstWhere('product_id', $product->id);
    $pcsBefore = collect($prodBefore['options'])->firstWhere('product_unit_id', $pcsUnit->id);
    expect((float) $pcsBefore['sell_price'])->toBe(3000.0);

    // Assign paket grosir ke customer.
    CustomerProductPricePackage::create([
        'customer_id' => $customer->id,
        'product_id' => $product->id,
        'price_package_id' => $grosir->id,
        'created_by' => $admin->id,
    ]);

    // Dengan customer_id → harga ikut paket customer (5000) untuk unit PCS.
    $res = $this->actingAs($admin)
        ->getJson(route('sales-orders.product-catalog', ['customer_id' => $customer->id]))
        ->assertOk()
        ->json('products');

    $prod = collect($res)->firstWhere('product_id', $product->id);
    $pcsOpt = collect($prod['options'])->firstWhere('product_unit_id', $pcsUnit->id);
    expect((float) $pcsOpt['sell_price'])->toBe(5000.0);
});

test('sync menolak paket yang bukan milik produk (cross-product)', function (): void {
    $admin = ppAdmin();
    ['product' => $product] = ppSeedProduct('A');
    ['product' => $product2] = ppSeedProduct('B');
    $customer = ppMakeCustomer();
    $foreignPkg = ProductPricePackage::where('product_id', $product2->id)->first();

    $this->actingAs($admin)
        ->put(route('customers.price-packages.sync', $customer->id), [
            'rows' => [['product_id' => $product->id, 'price_package_id' => $foreignPkg->id]],
        ])
        ->assertRedirect();

    expect(CustomerProductPricePackage::query()
        ->where('customer_id', $customer->id)
        ->where('product_id', $product->id)
        ->exists())->toBeFalse();
});
