<?php

use App\Models\Product;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\Product\ProductService;
use App\Services\Purchasing\GoodsReceiptService;
use App\Services\Purchasing\PurchaseOrderService;
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

function invAdmin(): User
{
    return User::query()->whereHas('role', fn ($q) => $q->where('code', Role::CODE_SUPERADMIN))->firstOrFail();
}

/**
 * Supplier + produk base PCS (qty_to_base=1) supaya angka pending = qty apa adanya.
 *
 * @return array{supplier: Supplier, product: Product}
 */
function invSupplierProduct(string $sku): array
{
    $admin = invAdmin();
    $supplier = Supplier::create(['code' => 'S-'.$sku, 'name' => 'Sup '.$sku, 'is_active' => true, 'created_by' => $admin->id]);
    $pcs = Unit::query()->where('name', 'PCS')->value('id');
    $product = app(ProductService::class)->create(
        ['supplier_id' => $supplier->id, 'sku' => $sku, 'name' => 'Prod '.$sku, 'is_active' => true, 'created_by' => $admin->id],
        [['unit_id' => $pcs, 'qty_to_base' => 1, 'barcode' => null]],
        [['name' => 'Harga Reguler', 'items' => [['unit_index' => 0, 'cost_price' => 1000, 'sell_price' => 1500]]]],
    );

    return ['supplier' => $supplier, 'product' => $product->fresh(['units'])];
}

function pendingOfProductInStockPage(User $admin, int $productId): int
{
    $response = test()->actingAs($admin)->get(route('stocks.index'));
    $response->assertOk();

    // Inertia menaruh props di data-page pada root div; ambil via assertInertia.
    $pending = 0;
    $response->assertInertia(function ($page) use ($productId, &$pending): void {
        $products = $page->toArray()['props']['products']['data'];
        $row = collect($products)->firstWhere('id', $productId);
        $pending = (int) ($row['pending_qty'] ?? 0);
    });

    return $pending;
}

test('pending penerimaan langsung muncul di halaman stok', function (): void {
    $admin = invAdmin();
    ['supplier' => $supplier, 'product' => $product] = invSupplierProduct('INV-DIRECT');
    $unit = $product->units->first();

    // Surat jalan 50, diterima 40 → pending 10.
    $grn = app(GoodsReceiptService::class)->createDraft(
        ['supplier_id' => $supplier->id, 'received_date' => now()->toDateString()],
        [[
            'po_item_id' => null,
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'batch_code' => 'B1',
            'qty_delivery_note' => 50,
            'qty_reguler' => 40,
            'cost_price' => 1000,
            'condition' => 'good',
        ]],
        $admin,
    );
    $svc = app(GoodsReceiptService::class);
    $svc->submit($grn, $admin);
    $svc->post($grn->refresh(), $admin);

    expect(pendingOfProductInStockPage($admin, $product->id))->toBe(10);
});

test('pending PO muncul di halaman stok & dijumlahkan dengan penerimaan langsung', function (): void {
    $admin = invAdmin();
    ['supplier' => $supplier, 'product' => $product] = invSupplierProduct('INV-MIX');
    $unit = $product->units->first();

    // PO 30, approve, belum di-GRN → pending PO 30.
    $po = app(PurchaseOrderService::class)->createDraft(
        ['supplier_id' => $supplier->id, 'po_date' => now()->toDateString()],
        [['product_id' => $product->id, 'product_unit_id' => $unit->id, 'qty_ordered' => 30, 'bonus_qty' => 0, 'cost_price' => 1000, 'discount_z1_pct' => 0, 'discount_z2_pct' => 0]],
        $admin,
    );
    app(PurchaseOrderService::class)->approve($po, $admin);

    // GRN langsung: surat jalan 20, diterima 15 → pending langsung 5.
    $grn = app(GoodsReceiptService::class)->createDraft(
        ['supplier_id' => $supplier->id, 'received_date' => now()->toDateString()],
        [[
            'po_item_id' => null,
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'batch_code' => 'B2',
            'qty_delivery_note' => 20,
            'qty_reguler' => 15,
            'cost_price' => 1000,
            'condition' => 'good',
        ]],
        $admin,
    );
    $svc = app(GoodsReceiptService::class);
    $svc->submit($grn, $admin);
    $svc->post($grn->refresh(), $admin);

    // Total pending = 30 (PO) + 5 (langsung) = 35.
    expect(pendingOfProductInStockPage($admin, $product->id))->toBe(35);
});
