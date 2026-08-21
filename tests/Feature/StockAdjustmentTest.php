<?php

use App\Exceptions\InsufficientStockException;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Role;
use App\Models\StockAdjustment;
use App\Models\StockBalance;
use App\Models\StockLedger;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\Inventory\StockAdjustmentService;
use App\Services\Product\ProductService;
use App\Services\Purchasing\GoodsReceiptService;
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

function adjAdmin(): User
{
    return User::query()->whereHas('role', fn ($q) => $q->where('code', Role::CODE_SUPERADMIN))->firstOrFail();
}

/**
 * Bikin produk + 1 batch berstok `qty` (base PCS) via GRN langsung posted.
 *
 * @return array{product: Product, batch: ProductBatch}
 */
function productWithStock(int $qty): array
{
    $admin = adjAdmin();
    $supplier = Supplier::create(['code' => 'S-ADJ', 'name' => 'Sup Adj', 'is_active' => true, 'created_by' => $admin->id]);
    $pcs = Unit::query()->where('name', 'PCS')->value('id');
    $product = app(ProductService::class)->create(
        ['supplier_id' => $supplier->id, 'sku' => 'ADJ-1', 'name' => 'Prod Adj', 'is_active' => true, 'created_by' => $admin->id],
        [['unit_id' => $pcs, 'qty_to_base' => 1, 'barcode' => null]],
        [['name' => 'Harga Reguler', 'items' => [['unit_index' => 0, 'cost_price' => 1000, 'sell_price' => 1500]]]],
    );
    $unit = $product->units()->first();

    $grn = app(GoodsReceiptService::class)->createDraft(
        ['supplier_id' => $supplier->id, 'received_date' => now()->toDateString()],
        [['po_item_id' => null, 'product_id' => $product->id, 'product_unit_id' => $unit->id, 'batch_code' => 'BATCH-ADJ', 'qty_reguler' => $qty, 'cost_price' => 1000, 'condition' => 'good']],
        $admin,
    );
    $svc = app(GoodsReceiptService::class);
    $svc->submit($grn, $admin);
    $svc->post($grn->refresh(), $admin);

    $batch = ProductBatch::query()->where('product_id', $product->id)->firstOrFail();

    return ['product' => $product, 'batch' => $batch];
}

function currentStock(int $productId): int
{
    return (int) StockBalance::query()->where('product_id', $productId)->sum('qty_on_hand');
}

test('adjustment OUT (barang rusak) mengurangi stok saat posting', function (): void {
    $admin = adjAdmin();
    ['product' => $product, 'batch' => $batch] = productWithStock(100);
    expect(currentStock($product->id))->toBe(100);

    $adj = app(StockAdjustmentService::class)->createDraft(
        ['adjustment_date' => now()->toDateString(), 'reason_category' => StockAdjustment::REASON_DAMAGED],
        [['product_id' => $product->id, 'batch_id' => $batch->id, 'direction' => 'out', 'qty' => 15, 'cost_price' => 1000]],
        $admin,
    );
    app(StockAdjustmentService::class)->post($adj, $admin);

    expect(currentStock($product->id))->toBe(85);
    expect($adj->refresh()->status)->toBe(StockAdjustment::STATUS_POSTED);
    expect(StockLedger::query()->where('type', StockLedger::TYPE_ADJUSTMENT_OUT)->where('product_id', $product->id)->exists())->toBeTrue();
});

test('adjustment IN (temuan lebih) menambah stok saat posting', function (): void {
    $admin = adjAdmin();
    ['product' => $product, 'batch' => $batch] = productWithStock(50);

    $adj = app(StockAdjustmentService::class)->createDraft(
        ['adjustment_date' => now()->toDateString(), 'reason_category' => StockAdjustment::REASON_FOUND],
        [['product_id' => $product->id, 'batch_id' => $batch->id, 'direction' => 'in', 'qty' => 20]],
        $admin,
    );
    app(StockAdjustmentService::class)->post($adj, $admin);

    expect(currentStock($product->id))->toBe(70);
    expect(StockLedger::query()->where('type', StockLedger::TYPE_ADJUSTMENT_IN)->where('product_id', $product->id)->exists())->toBeTrue();
});

test('adjustment OUT melebihi stok ditolak saat posting (stok tak berubah)', function (): void {
    $admin = adjAdmin();
    ['product' => $product, 'batch' => $batch] = productWithStock(10);

    $adj = app(StockAdjustmentService::class)->createDraft(
        ['adjustment_date' => now()->toDateString(), 'reason_category' => StockAdjustment::REASON_LOST],
        [['product_id' => $product->id, 'batch_id' => $batch->id, 'direction' => 'out', 'qty' => 25]],
        $admin,
    );

    expect(fn () => app(StockAdjustmentService::class)->post($adj, $admin))
        ->toThrow(InsufficientStockException::class);

    // Stok tetap 10, adjustment tetap draft (transaksi rollback).
    expect(currentStock($product->id))->toBe(10);
    expect($adj->refresh()->status)->toBe(StockAdjustment::STATUS_DRAFT);
});

test('adjustment yang sudah posted tidak bisa diposting/dibatalkan lagi', function (): void {
    $admin = adjAdmin();
    ['product' => $product, 'batch' => $batch] = productWithStock(30);

    $adj = app(StockAdjustmentService::class)->createDraft(
        ['adjustment_date' => now()->toDateString(), 'reason_category' => StockAdjustment::REASON_SHRINKAGE],
        [['product_id' => $product->id, 'batch_id' => $batch->id, 'direction' => 'out', 'qty' => 5]],
        $admin,
    );
    app(StockAdjustmentService::class)->post($adj, $admin);

    expect($adj->refresh()->canBePosted())->toBeFalse();
    expect($adj->canBeCancelled())->toBeFalse();
    expect($adj->canBeEdited())->toBeFalse();
});

test('adjustment dengan satuan besar dikonversi ke base (2 KRT = 24 PCS)', function (): void {
    $admin = adjAdmin();
    $supplier = Supplier::create(['code' => 'S-ADJ2', 'name' => 'Sup Adj2', 'is_active' => true, 'created_by' => $admin->id]);
    $pcs = Unit::query()->where('name', 'PCS')->value('id');
    $krt = Unit::query()->where('name', 'KARDUS')->value('id');

    // Produk PCS(base) + KARDUS(x12), stok 120 PCS via GRN.
    $product = app(ProductService::class)->create(
        ['supplier_id' => $supplier->id, 'sku' => 'ADJ-KRT', 'name' => 'Prod KRT', 'is_active' => true, 'created_by' => $admin->id],
        [
            ['unit_id' => $pcs, 'qty_to_base' => 1, 'barcode' => null],
            ['unit_id' => $krt, 'qty_to_base' => 12, 'barcode' => null],
        ],
        [['name' => 'Harga Reguler', 'items' => [
            ['unit_index' => 0, 'cost_price' => 1000, 'sell_price' => 1500],
            ['unit_index' => 1, 'cost_price' => 12000, 'sell_price' => 18000],
        ]]],
    )->fresh(['units']);
    $pcsUnit = $product->units->firstWhere('qty_to_base', 1);
    $krtUnit = $product->units->firstWhere('qty_to_base', 12);

    $grn = app(GoodsReceiptService::class)->createDraft(
        ['supplier_id' => $supplier->id, 'received_date' => now()->toDateString()],
        [['po_item_id' => null, 'product_id' => $product->id, 'product_unit_id' => $pcsUnit->id, 'batch_code' => 'BATCH-KRT', 'qty_reguler' => 120, 'cost_price' => 1000, 'condition' => 'good']],
        $admin,
    );
    $gsvc = app(GoodsReceiptService::class);
    $gsvc->submit($grn, $admin);
    $gsvc->post($grn->refresh(), $admin);
    $batch = ProductBatch::query()->where('product_id', $product->id)->firstOrFail();

    expect(currentStock($product->id))->toBe(120);

    // Adjustment OUT 2 KARDUS → harus potong 24 PCS base.
    $adj = app(StockAdjustmentService::class)->createDraft(
        ['adjustment_date' => now()->toDateString(), 'reason_category' => StockAdjustment::REASON_DAMAGED],
        [['product_id' => $product->id, 'product_unit_id' => $krtUnit->id, 'batch_id' => $batch->id, 'direction' => 'out', 'qty' => 2, 'cost_price' => 12000]],
        $admin,
    );

    // Item menyimpan qty (dalam KRT) + qty_base (konversi).
    $item = $adj->items()->first();
    expect((int) $item->qty)->toBe(2);
    expect((int) $item->qty_base)->toBe(24);
    expect($item->product_unit_name_snapshot)->toBe('KARDUS');

    app(StockAdjustmentService::class)->post($adj, $admin);

    // Stok 120 − 24 = 96.
    expect(currentStock($product->id))->toBe(96);
});

test('adjustment tolak satuan yang bukan milik produk', function (): void {
    $admin = adjAdmin();
    ['product' => $product, 'batch' => $batch] = productWithStock(50);

    // product_unit_id yang tidak ada / bukan milik produk ini → ditolak service.
    expect(fn () => app(StockAdjustmentService::class)->createDraft(
        ['adjustment_date' => now()->toDateString(), 'reason_category' => StockAdjustment::REASON_DAMAGED],
        [['product_id' => $product->id, 'product_unit_id' => 999999, 'batch_id' => $batch->id, 'direction' => 'out', 'qty' => 1]],
        $admin,
    ))->toThrow(ValidationException::class);
});

test('HTTP: buat draft lalu posting via endpoint menambah stok', function (): void {
    $admin = adjAdmin();
    ['product' => $product, 'batch' => $batch] = productWithStock(40);

    $this->actingAs($admin)->post(route('adjustments.store'), [
        'adjustment_date' => now()->toDateString(),
        'reason_category' => StockAdjustment::REASON_MISCOUNT,
        'items' => [['product_id' => $product->id, 'batch_id' => $batch->id, 'direction' => 'in', 'qty' => 8]],
    ])->assertRedirect();

    $adj = StockAdjustment::query()->latest('id')->firstOrFail();
    $this->actingAs($admin)->post(route('adjustments.post', $adj->id))->assertRedirect();

    expect(currentStock($product->id))->toBe(48);
});
