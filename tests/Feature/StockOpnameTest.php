<?php

use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Role;
use App\Models\StockBalance;
use App\Models\StockLedger;
use App\Models\StockOpname;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\Inventory\StockAdjustmentService;
use App\Services\Inventory\StockOpnameService;
use App\Services\Product\ProductService;
use App\Services\Purchasing\GoodsReceiptService;
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

function opnameAdmin(): User
{
    return User::query()->whereHas('role', fn ($q) => $q->where('code', Role::CODE_SUPERADMIN))->firstOrFail();
}

/**
 * @return array{product: Product, batch: ProductBatch}
 */
function opnameProductWithStock(int $qty, string $sku = 'OPN-1'): array
{
    $admin = opnameAdmin();
    $supplier = Supplier::create(['code' => 'S-'.$sku, 'name' => 'Sup '.$sku, 'is_active' => true, 'created_by' => $admin->id]);
    $pcs = Unit::query()->where('name', 'PCS')->value('id');
    $product = app(ProductService::class)->create(
        ['supplier_id' => $supplier->id, 'sku' => $sku, 'name' => 'Prod '.$sku, 'is_active' => true, 'created_by' => $admin->id],
        [['unit_id' => $pcs, 'qty_to_base' => 1, 'barcode' => null]],
        [['name' => 'Harga Reguler', 'items' => [['unit_index' => 0, 'cost_price' => 1000, 'sell_price' => 1500]]]],
    );
    $unit = $product->units()->first();

    $grn = app(GoodsReceiptService::class)->createDraft(
        ['supplier_id' => $supplier->id, 'received_date' => now()->toDateString()],
        [['po_item_id' => null, 'product_id' => $product->id, 'product_unit_id' => $unit->id, 'batch_code' => 'BATCH-'.$sku, 'qty_reguler' => $qty, 'cost_price' => 1000, 'condition' => 'good']],
        $admin,
    );
    $svc = app(GoodsReceiptService::class);
    $svc->submit($grn, $admin);
    $svc->post($grn->refresh(), $admin);

    return ['product' => $product, 'batch' => ProductBatch::query()->where('product_id', $product->id)->firstOrFail()];
}

function opnameStock(int $productId): int
{
    return (int) StockBalance::query()->where('product_id', $productId)->sum('qty_on_hand');
}

test('opname auto-generate menarik semua batch berstok', function (): void {
    $admin = opnameAdmin();
    ['product' => $pA] = opnameProductWithStock(50, 'OPN-A');
    ['product' => $pB] = opnameProductWithStock(30, 'OPN-B');

    $opname = app(StockOpnameService::class)->createDraft(['opname_date' => now()->toDateString()], $admin);

    expect($opname->items()->count())->toBe(2);
    $skus = $opname->items->pluck('product_sku_snapshot')->sort()->values()->all();
    expect($skus)->toBe(['OPN-A', 'OPN-B']);
    expect((int) $opname->items->firstWhere('product_id', $pA->id)->system_qty_snapshot)->toBe(50);
    expect((int) $opname->items->firstWhere('product_id', $pB->id)->system_qty_snapshot)->toBe(30);
});

test('posting opname menyesuaikan stok ke hasil hitung (kurang)', function (): void {
    $admin = opnameAdmin();
    ['product' => $product] = opnameProductWithStock(100);

    $opname = app(StockOpnameService::class)->createDraft(['opname_date' => now()->toDateString()], $admin);
    $item = $opname->items()->first();

    app(StockOpnameService::class)->update($opname, ['opname_date' => now()->toDateString()], [$item->id => 90], $admin);
    app(StockOpnameService::class)->post($opname->refresh(), $admin);

    expect(opnameStock($product->id))->toBe(90);
    expect((int) $item->refresh()->variance)->toBe(-10);
    expect($opname->refresh()->status)->toBe(StockOpname::STATUS_POSTED);
    expect(StockLedger::query()->where('type', StockLedger::TYPE_OPNAME_OUT)->where('product_id', $product->id)->exists())->toBeTrue();
});

test('posting opname dengan fisik lebih banyak menambah stok', function (): void {
    $admin = opnameAdmin();
    ['product' => $product] = opnameProductWithStock(40);

    $opname = app(StockOpnameService::class)->createDraft(['opname_date' => now()->toDateString()], $admin);
    $item = $opname->items()->first();

    app(StockOpnameService::class)->update($opname, ['opname_date' => now()->toDateString()], [$item->id => 55], $admin);
    app(StockOpnameService::class)->post($opname->refresh(), $admin);

    expect(opnameStock($product->id))->toBe(55);
    expect(StockLedger::query()->where('type', StockLedger::TYPE_OPNAME_IN)->where('product_id', $product->id)->exists())->toBeTrue();
});

test('variance dihitung ulang terhadap stok TERKINI, bukan snapshot saat opname dibuat', function (): void {
    $admin = opnameAdmin();
    ['product' => $product, 'batch' => $batch] = opnameProductWithStock(100);

    $opname = app(StockOpnameService::class)->createDraft(['opname_date' => now()->toDateString()], $admin);
    $item = $opname->items()->first();
    expect((int) $item->system_qty_snapshot)->toBe(100);

    app(StockOpnameService::class)->update($opname, ['opname_date' => now()->toDateString()], [$item->id => 100], $admin);

    // Sebelum posting, stok berubah jadi 80 lewat adjustment OUT.
    $adj = app(StockAdjustmentService::class)->createDraft(
        ['adjustment_date' => now()->toDateString(), 'reason_category' => 'lost'],
        [['product_id' => $product->id, 'batch_id' => $batch->id, 'direction' => 'out', 'qty' => 20]],
        $admin,
    );
    app(StockAdjustmentService::class)->post($adj, $admin);
    expect(opnameStock($product->id))->toBe(80);

    // Posting opname: fisik 100 vs stok terkini 80 → +20 → jadi 100.
    app(StockOpnameService::class)->post($opname->refresh(), $admin);
    expect(opnameStock($product->id))->toBe(100);
    expect((int) $item->refresh()->variance)->toBe(20);
});

test('item belum dihitung / variance 0 tidak menulis ledger', function (): void {
    $admin = opnameAdmin();
    ['product' => $product] = opnameProductWithStock(60);

    $opname = app(StockOpnameService::class)->createDraft(['opname_date' => now()->toDateString()], $admin);
    $item = $opname->items()->first();

    app(StockOpnameService::class)->update($opname, ['opname_date' => now()->toDateString()], [$item->id => 60], $admin);
    app(StockOpnameService::class)->post($opname->refresh(), $admin);

    expect(opnameStock($product->id))->toBe(60);
    expect(StockLedger::query()->whereIn('type', [StockLedger::TYPE_OPNAME_IN, StockLedger::TYPE_OPNAME_OUT])->exists())->toBeFalse();
});

test('HTTP: buat opname, isi hitung, posting menyesuaikan stok', function (): void {
    $admin = opnameAdmin();
    ['product' => $product] = opnameProductWithStock(70);

    $this->actingAs($admin)->post(route('opnames.store'), ['opname_date' => now()->toDateString()])->assertRedirect();
    $opname = StockOpname::query()->latest('id')->firstOrFail();
    $item = $opname->items()->first();

    $this->actingAs($admin)->put(route('opnames.update', $opname->id), [
        'opname_date' => now()->toDateString(),
        'items' => [['id' => $item->id, 'counted_qty' => 65]],
    ])->assertRedirect();

    $this->actingAs($admin)->post(route('opnames.post', $opname->id))->assertRedirect();

    expect(opnameStock($product->id))->toBe(65);
});
