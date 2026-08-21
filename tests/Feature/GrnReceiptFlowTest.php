<?php

use App\Models\GoodsReceipt;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\StockBalance;
use App\Models\StockLedger;
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
use Illuminate\Validation\ValidationException;

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

function grnSuperadmin(): User
{
    return User::query()->whereHas('role', fn ($q) => $q->where('code', Role::CODE_SUPERADMIN))->firstOrFail();
}

/**
 * Bikin 1 supplier + 1 produk (PCS base + KARDUS x40) dgn paket harga.
 *
 * @return array{supplier: Supplier, product: Product}
 */
function makeSupplierWithProduct(): array
{
    $admin = grnSuperadmin();

    $supplier = Supplier::create([
        'code' => 'SUP-T1',
        'name' => 'Supplier Test',
        'is_active' => true,
        'created_by' => $admin->id,
    ]);

    $pcs = Unit::query()->where('name', 'PCS')->value('id');
    $kardus = Unit::query()->where('name', 'KARDUS')->value('id');

    $product = app(ProductService::class)->create(
        [
            'supplier_id' => $supplier->id,
            'sku' => 'TST-001',
            'name' => 'Produk Test',
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
                ['unit_index' => 0, 'cost_price' => 2500, 'sell_price' => 3000],
                ['unit_index' => 1, 'cost_price' => 100000, 'sell_price' => 120000],
            ],
        ]],
    );

    return ['supplier' => $supplier, 'product' => $product->fresh(['units'])];
}

test('GRN dari PO: posting menambah stok & menutup PO', function (): void {
    $admin = grnSuperadmin();
    ['supplier' => $supplier, 'product' => $product] = makeSupplierWithProduct();
    $kardusUnit = $product->units->firstWhere('qty_to_base', 40);

    // Buat PO 5 KARDUS lalu approve.
    $po = app(PurchaseOrderService::class)->createDraft(
        [
            'supplier_id' => $supplier->id,
            'po_date' => now()->toDateString(),
        ],
        [[
            'product_id' => $product->id,
            'product_unit_id' => $kardusUnit->id,
            'qty_ordered' => 5,
            'bonus_qty' => 0,
            'cost_price' => 100000,
            'discount_z1_pct' => 0,
            'discount_z2_pct' => 0,
        ]],
        $admin,
    );
    app(PurchaseOrderService::class)->approve($po, $admin);
    $poItem = $po->items()->first();

    // Buat GRN dari PO via HTTP, terima penuh 5 KARDUS.
    $this->actingAs($admin)->post(route('grns.store'), [
        'purchase_order_id' => $po->id,
        'received_date' => now()->toDateString(),
        'items' => [[
            'po_item_id' => $poItem->id,
            'product_id' => $product->id,
            'product_unit_id' => $kardusUnit->id,
            'batch_code' => 'BATCH-A',
            'qty_reguler' => 5,
            'qty_bonus' => 0,
            'qty_damaged' => 0,
            'cost_price' => 100000,
            'condition' => 'good',
        ]],
    ])->assertRedirect();

    $grn = GoodsReceipt::query()->latest('id')->firstOrFail();
    expect($grn->purchase_order_id)->toBe($po->id);

    $service = app(GoodsReceiptService::class);
    $service->submit($grn, $admin);
    $service->post($grn->refresh(), $admin);

    // Stok base = 5 KARDUS x 40 = 200 PCS.
    $balance = StockBalance::query()->where('product_id', $product->id)->sum('qty_on_hand');
    expect((int) $balance)->toBe(200);
    expect(StockLedger::query()->where('product_id', $product->id)->where('type', StockLedger::TYPE_PURCHASE_IN)->exists())->toBeTrue();

    // PO tertutup penuh.
    expect($po->refresh()->status)->toBe(PurchaseOrder::STATUS_CLOSED);
    expect((int) $poItem->refresh()->qty_received)->toBe(5);
});

test('GRN langsung tanpa PO: posting menambah stok tanpa menyentuh PO', function (): void {
    $admin = grnSuperadmin();
    ['supplier' => $supplier, 'product' => $product] = makeSupplierWithProduct();
    $pcsUnit = $product->units->firstWhere('qty_to_base', 1);

    // Penerimaan langsung: tanpa purchase_order_id, supplier + produk manual.
    $this->actingAs($admin)->post(route('grns.store'), [
        'purchase_order_id' => null,
        'supplier_id' => $supplier->id,
        'received_date' => now()->toDateString(),
        'items' => [[
            'po_item_id' => null,
            'product_id' => $product->id,
            'product_unit_id' => $pcsUnit->id,
            'batch_code' => 'BATCH-DIRECT',
            'qty_reguler' => 30,
            'qty_bonus' => 0,
            'qty_damaged' => 0,
            'cost_price' => 2500,
            'condition' => 'good',
        ]],
    ])->assertRedirect();

    $grn = GoodsReceipt::query()->latest('id')->firstOrFail();
    expect($grn->purchase_order_id)->toBeNull();
    expect((int) $grn->supplier_id)->toBe($supplier->id);
    expect($grn->items()->first()->po_item_id)->toBeNull();

    $service = app(GoodsReceiptService::class);
    $service->submit($grn, $admin);
    $service->post($grn->refresh(), $admin);

    // Stok base = 30 PCS x 1 = 30.
    $balance = StockBalance::query()->where('product_id', $product->id)->sum('qty_on_hand');
    expect((int) $balance)->toBe(30);
    expect($grn->refresh()->status)->toBe(GoodsReceipt::STATUS_POSTED);
    expect(PurchaseOrder::query()->count())->toBe(0);
});

test('GRN langsung: surat jalan > diterima → pending tercatat, stok hanya yang diterima', function (): void {
    $admin = grnSuperadmin();
    ['supplier' => $supplier, 'product' => $product] = makeSupplierWithProduct();
    $pcsUnit = $product->units->firstWhere('qty_to_base', 1);

    // Surat jalan 50, tapi yang turun cuma 40 → pending 10, stok 40.
    $this->actingAs($admin)->post(route('grns.store'), [
        'purchase_order_id' => null,
        'supplier_id' => $supplier->id,
        'received_date' => now()->toDateString(),
        'items' => [[
            'po_item_id' => null,
            'product_id' => $product->id,
            'product_unit_id' => $pcsUnit->id,
            'batch_code' => 'BATCH-SJ',
            'qty_delivery_note' => 50,
            'qty_reguler' => 40,
            'qty_bonus' => 0,
            'qty_damaged' => 0,
            'cost_price' => 2500,
            'condition' => 'good',
        ]],
    ])->assertRedirect();

    $grn = GoodsReceipt::query()->latest('id')->firstOrFail();
    $item = $grn->items()->first();
    expect((int) $item->qty_delivery_note)->toBe(50);
    expect((int) $item->qty_reguler)->toBe(40);
    // Pending = surat jalan − diterima = 10.
    expect((int) $item->qty_delivery_note - (int) $item->qty_reguler)->toBe(10);

    $service = app(GoodsReceiptService::class);
    $service->submit($grn, $admin);
    $service->post($grn->refresh(), $admin);

    // Stok hanya yang benar-benar diterima (40), BUKAN surat jalan (50).
    $balance = StockBalance::query()->where('product_id', $product->id)->sum('qty_on_hand');
    expect((int) $balance)->toBe(40);
});

test('GRN langsung menolak surat jalan lebih kecil dari qty diterima', function (): void {
    $admin = grnSuperadmin();
    ['supplier' => $supplier, 'product' => $product] = makeSupplierWithProduct();
    $pcsUnit = $product->units->firstWhere('qty_to_base', 1);

    $this->actingAs($admin)->post(route('grns.store'), [
        'purchase_order_id' => null,
        'supplier_id' => $supplier->id,
        'received_date' => now()->toDateString(),
        'items' => [[
            'po_item_id' => null,
            'product_id' => $product->id,
            'product_unit_id' => $pcsUnit->id,
            'batch_code' => 'BATCH-BAD',
            'qty_delivery_note' => 5,
            'qty_reguler' => 10, // diterima > surat jalan → tidak masuk akal
            'cost_price' => 2500,
            'condition' => 'good',
        ]],
    ])->assertSessionHasErrors('items.0.qty_delivery_note');

    expect(GoodsReceipt::query()->count())->toBe(0);
});

test('GRN langsung menolak produk yang bukan milik supplier terpilih', function (): void {
    $admin = grnSuperadmin();
    ['product' => $productA] = makeSupplierWithProduct();

    // Supplier B berbeda; produk A bukan miliknya.
    $supplierB = Supplier::create([
        'code' => 'SUP-T2',
        'name' => 'Supplier Lain',
        'is_active' => true,
        'created_by' => $admin->id,
    ]);
    $pcsUnit = $productA->units->firstWhere('qty_to_base', 1);

    $this->actingAs($admin)->post(route('grns.store'), [
        'purchase_order_id' => null,
        'supplier_id' => $supplierB->id,
        'received_date' => now()->toDateString(),
        'items' => [[
            'po_item_id' => null,
            'product_id' => $productA->id,
            'product_unit_id' => $pcsUnit->id,
            'batch_code' => 'BATCH-X',
            'qty_reguler' => 5,
            'qty_bonus' => 0,
            'qty_damaged' => 0,
            'cost_price' => 2500,
            'condition' => 'good',
        ]],
    ])->assertSessionHasErrors('items.0.product_id');

    expect(GoodsReceipt::query()->count())->toBe(0);
});

test('GRN langsung wajib mengisi supplier', function (): void {
    $admin = grnSuperadmin();
    ['product' => $product] = makeSupplierWithProduct();
    $pcsUnit = $product->units->firstWhere('qty_to_base', 1);

    $this->actingAs($admin)->post(route('grns.store'), [
        'purchase_order_id' => null,
        'supplier_id' => null,
        'received_date' => now()->toDateString(),
        'items' => [[
            'po_item_id' => null,
            'product_id' => $product->id,
            'product_unit_id' => $pcsUnit->id,
            'batch_code' => 'BATCH-Y',
            'qty_reguler' => 5,
            'cost_price' => 2500,
            'condition' => 'good',
        ]],
    ])->assertSessionHasErrors('supplier_id');
});

/**
 * Bikin GRN penerimaan langsung posted dengan pending (surat jalan > diterima).
 *
 * @return array{grn: GoodsReceipt, product: Product}
 */
function makeDirectGrnWithPending(int $suratJalan, int $diterima): array
{
    $admin = grnSuperadmin();
    ['supplier' => $supplier, 'product' => $product] = makeSupplierWithProduct();
    $pcsUnit = $product->units->firstWhere('qty_to_base', 1);

    $grn = app(GoodsReceiptService::class)->createDraft(
        ['supplier_id' => $supplier->id, 'received_date' => now()->toDateString()],
        [[
            'po_item_id' => null,
            'product_id' => $product->id,
            'product_unit_id' => $pcsUnit->id,
            'batch_code' => 'BATCH-PEND',
            'qty_delivery_note' => $suratJalan,
            'qty_reguler' => $diterima,
            'cost_price' => 2500,
            'condition' => 'good',
        ]],
        $admin,
    );
    $svc = app(GoodsReceiptService::class);
    $svc->submit($grn, $admin);
    $svc->post($grn->refresh(), $admin);

    return ['grn' => $grn, 'product' => $product];
}

function stockPagePending(User $admin, int $productId): int
{
    $pending = 0;
    test()->actingAs($admin)->get(route('stocks.index'))->assertOk()->assertInertia(function ($page) use ($productId, &$pending): void {
        $row = collect($page->toArray()['props']['products']['data'])->firstWhere('id', $productId);
        $pending = (int) ($row['pending_qty'] ?? 0);
    });

    return $pending;
}

test('tandai pending item selesai bikin pending item itu hilang dari stok', function (): void {
    $admin = grnSuperadmin();
    ['grn' => $grn, 'product' => $product] = makeDirectGrnWithPending(50, 40);
    $item = $grn->items()->first();

    // Sebelum di-settle: pending 10 muncul di stok.
    expect(stockPagePending($admin, $product->id))->toBe(10);
    expect($item->hasUnsettledPending())->toBeTrue();

    // Tandai item selesai.
    $this->actingAs($admin)->post(route('grn-items.settle-pending', $item->id))->assertRedirect();

    // Pending hilang dari stok.
    expect(stockPagePending($admin, $product->id))->toBe(0);
    expect($item->refresh()->pending_settled_at)->not->toBeNull();
    expect($item->hasUnsettledPending())->toBeFalse();
});

test('batal tandai pending item memunculkan pending lagi di stok', function (): void {
    $admin = grnSuperadmin();
    ['grn' => $grn, 'product' => $product] = makeDirectGrnWithPending(30, 20);
    $item = $grn->items()->first();

    $this->actingAs($admin)->post(route('grn-items.settle-pending', $item->id))->assertRedirect();
    expect(stockPagePending($admin, $product->id))->toBe(0);

    // Batalkan → pending 10 muncul lagi.
    $this->actingAs($admin)->post(route('grn-items.unsettle-pending', $item->id))->assertRedirect();
    expect(stockPagePending($admin, $product->id))->toBe(10);
    expect($item->refresh()->pending_settled_at)->toBeNull();
});

test('settle per item: item lain tetap pending (tidak semua barang datang bareng)', function (): void {
    $admin = grnSuperadmin();
    ['supplier' => $supplier, 'product' => $pA] = makeSupplierWithProduct();

    // Produk kedua dari supplier yang sama.
    $pcs = Unit::query()->where('name', 'PCS')->value('id');
    $pB = app(ProductService::class)->create(
        ['supplier_id' => $supplier->id, 'sku' => 'TST-002', 'name' => 'Produk B', 'is_active' => true, 'created_by' => $admin->id],
        [['unit_id' => $pcs, 'qty_to_base' => 1, 'barcode' => null]],
        [['name' => 'Harga Reguler', 'items' => [['unit_index' => 0, 'cost_price' => 1000, 'sell_price' => 1500]]]],
    )->fresh(['units']);

    $uA = $pA->units->firstWhere('qty_to_base', 1);
    $uB = $pB->units->firstWhere('qty_to_base', 1);

    // 1 GRN langsung, 2 item: A pending 13, B pending 20.
    $grn = app(GoodsReceiptService::class)->createDraft(
        ['supplier_id' => $supplier->id, 'received_date' => now()->toDateString()],
        [
            ['po_item_id' => null, 'product_id' => $pA->id, 'product_unit_id' => $uA->id, 'batch_code' => 'BA', 'qty_delivery_note' => 53, 'qty_reguler' => 40, 'cost_price' => 1000, 'condition' => 'good'],
            ['po_item_id' => null, 'product_id' => $pB->id, 'product_unit_id' => $uB->id, 'batch_code' => 'BB', 'qty_delivery_note' => 100, 'qty_reguler' => 80, 'cost_price' => 1000, 'condition' => 'good'],
        ],
        $admin,
    );
    $svc = app(GoodsReceiptService::class);
    $svc->submit($grn, $admin);
    $svc->post($grn->refresh(), $admin);

    expect(stockPagePending($admin, $pA->id))->toBe(13);
    expect(stockPagePending($admin, $pB->id))->toBe(20);

    // Barang A menyusul → tandai item A selesai. B tetap pending.
    $itemA = $grn->items()->where('product_id', $pA->id)->first();
    $this->actingAs($admin)->post(route('grn-items.settle-pending', $itemA->id))->assertRedirect();

    expect(stockPagePending($admin, $pA->id))->toBe(0);  // A beres
    expect(stockPagePending($admin, $pB->id))->toBe(20); // B masih pending
});

test('tidak bisa tandai selesai kalau item tidak punya pending', function (): void {
    $admin = grnSuperadmin();
    ['grn' => $grn] = makeDirectGrnWithPending(20, 20); // surat jalan = diterima
    $item = $grn->items()->first();

    expect($item->hasUnsettledPending())->toBeFalse();

    expect(fn () => app(GoodsReceiptService::class)->settleItemPending($item, $admin))
        ->toThrow(ValidationException::class);
});
