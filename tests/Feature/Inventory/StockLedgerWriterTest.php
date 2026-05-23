<?php

use App\Exceptions\InsufficientStockException;
use App\Models\ProductBatch;
use App\Models\ProductCategory;
use App\Models\ProductUnit;
use App\Models\StockBalance;
use App\Models\StockLedger;
use App\Services\Inventory\StockLedgerWriter;
use App\Services\Product\ProductService;
use App\Services\Setting\SettingManager;
use Database\Seeders\PriceTierSeeder;
use Database\Seeders\ProductCategorySeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(SettingSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(ProductCategorySeeder::class);
    $this->seed(PriceTierSeeder::class);

    $this->writer = app(StockLedgerWriter::class);
});

function ledgerFreshProduct(): array
{
    $product = app(ProductService::class)->create(
        [
            'name' => 'Test Ledger '.random_int(100, 999),
            'category_id' => ProductCategory::where('code', 'MIE')->value('id'),
        ],
        [['level' => ProductUnit::LEVEL_KCL, 'name' => 'Pcs', 'qty_to_base' => 1]],
    );

    $batch = ProductBatch::create([
        'product_id' => $product->id,
        'batch_code' => 'BATCH-'.random_int(1000, 9999),
        'is_active' => true,
    ]);

    return [$product, $batch];
}

test('writeIn membuat ledger purchase_in dan upsert balance', function (): void {
    [$product, $batch] = ledgerFreshProduct();
    $baseUnit = $product->units->first();

    $ledger = $this->writer->writeIn([
        'product_id' => $product->id,
        'batch_id' => $batch->id,
        'product_unit_id' => $baseUnit->id,
        'type' => StockLedger::TYPE_PURCHASE_IN,
        'qty_in' => 100,
        'cost_price' => 5000,
        'ref_type' => 'GRN',
        'ref_id' => 1,
    ]);

    expect($ledger->qty_in)->toBe(100);
    expect((float) $ledger->cost_price)->toBe(5000.0);

    $balance = StockBalance::where('batch_id', $batch->id)->first();
    expect($balance)->not->toBeNull();
    expect($balance->qty_on_hand)->toBe(100);
    expect($balance->qty_bonus_pool)->toBe(0);
});

test('writeIn bonus_pool menambah qty_bonus_pool', function (): void {
    [$product, $batch] = ledgerFreshProduct();
    $baseUnit = $product->units->first();

    $this->writer->writeIn([
        'product_id' => $product->id,
        'batch_id' => $batch->id,
        'product_unit_id' => $baseUnit->id,
        'type' => StockLedger::TYPE_BONUS_IN,
        'is_bonus_pool' => true,
        'qty_in' => 20,
        'cost_price' => 0,
        'ref_type' => 'GRN',
        'ref_id' => 1,
    ]);

    $balance = StockBalance::where('batch_id', $batch->id)->first();
    expect($balance->qty_on_hand)->toBe(20);
    expect($balance->qty_bonus_pool)->toBe(20);
});

test('writeOut mengurangi balance', function (): void {
    [$product, $batch] = ledgerFreshProduct();
    $baseUnit = $product->units->first();

    $this->writer->writeIn([
        'product_id' => $product->id, 'batch_id' => $batch->id, 'product_unit_id' => $baseUnit->id,
        'type' => StockLedger::TYPE_PURCHASE_IN, 'qty_in' => 100, 'cost_price' => 5000,
        'ref_type' => 'GRN', 'ref_id' => 1,
    ]);

    $this->writer->writeOut([
        'product_id' => $product->id, 'batch_id' => $batch->id, 'product_unit_id' => $baseUnit->id,
        'type' => StockLedger::TYPE_SALE_OUT, 'qty_out' => 30,
        'ref_type' => 'DO', 'ref_id' => 1,
    ]);

    expect(StockBalance::where('batch_id', $batch->id)->value('qty_on_hand'))->toBe(70);
});

test('writeOut stok kurang throw InsufficientStockException', function (): void {
    [$product, $batch] = ledgerFreshProduct();
    $baseUnit = $product->units->first();

    $this->writer->writeIn([
        'product_id' => $product->id, 'batch_id' => $batch->id, 'product_unit_id' => $baseUnit->id,
        'type' => StockLedger::TYPE_PURCHASE_IN, 'qty_in' => 10, 'cost_price' => 5000,
        'ref_type' => 'GRN', 'ref_id' => 1,
    ]);

    expect(fn () => $this->writer->writeOut([
        'product_id' => $product->id, 'batch_id' => $batch->id, 'product_unit_id' => $baseUnit->id,
        'type' => StockLedger::TYPE_SALE_OUT, 'qty_out' => 50,
        'ref_type' => 'DO', 'ref_id' => 1,
    ]))->toThrow(InsufficientStockException::class);
});

test('writeOut allow_negative_stock=true tetap lolos walau kurang', function (): void {
    app(SettingManager::class)->set('inventory.allow_negative_stock', true);
    app(SettingManager::class)->forgetCache();

    [$product, $batch] = ledgerFreshProduct();
    $baseUnit = $product->units->first();

    $this->writer->writeOut([
        'product_id' => $product->id, 'batch_id' => $batch->id, 'product_unit_id' => $baseUnit->id,
        'type' => StockLedger::TYPE_SALE_OUT, 'qty_out' => 50,
        'ref_type' => 'DO', 'ref_id' => 1,
    ]);

    expect(StockBalance::where('batch_id', $batch->id)->value('qty_on_hand'))->toBe(-50);
});

test('tipe ledger invalid throw InvalidArgumentException', function (): void {
    [$product, $batch] = ledgerFreshProduct();
    $baseUnit = $product->units->first();

    expect(fn () => $this->writer->writeIn([
        'product_id' => $product->id, 'batch_id' => $batch->id, 'product_unit_id' => $baseUnit->id,
        'type' => 'gibberish', 'qty_in' => 1, 'cost_price' => 0,
        'ref_type' => 'XX', 'ref_id' => 1,
    ]))->toThrow(InvalidArgumentException::class);
});

test('qty_in=0 untuk writeIn ditolak', function (): void {
    [$product, $batch] = ledgerFreshProduct();
    $baseUnit = $product->units->first();

    expect(fn () => $this->writer->writeIn([
        'product_id' => $product->id, 'batch_id' => $batch->id, 'product_unit_id' => $baseUnit->id,
        'type' => StockLedger::TYPE_PURCHASE_IN, 'qty_in' => 0, 'cost_price' => 0,
        'ref_type' => 'GRN', 'ref_id' => 1,
    ]))->toThrow(InvalidArgumentException::class);
});

test('multi-IN cumulative ke balance benar', function (): void {
    [$product, $batch] = ledgerFreshProduct();
    $baseUnit = $product->units->first();

    foreach ([10, 20, 30] as $qty) {
        $this->writer->writeIn([
            'product_id' => $product->id, 'batch_id' => $batch->id, 'product_unit_id' => $baseUnit->id,
            'type' => StockLedger::TYPE_PURCHASE_IN, 'qty_in' => $qty, 'cost_price' => 100,
            'ref_type' => 'GRN', 'ref_id' => 1,
        ]);
    }

    expect(StockBalance::where('batch_id', $batch->id)->value('qty_on_hand'))->toBe(60);
    expect(StockLedger::where('batch_id', $batch->id)->count())->toBe(3);
});

test('findInconsistencies detect drift antara ledger & balance', function (): void {
    [$product, $batch] = ledgerFreshProduct();
    $baseUnit = $product->units->first();

    $this->writer->writeIn([
        'product_id' => $product->id, 'batch_id' => $batch->id, 'product_unit_id' => $baseUnit->id,
        'type' => StockLedger::TYPE_PURCHASE_IN, 'qty_in' => 100, 'cost_price' => 1000,
        'ref_type' => 'GRN', 'ref_id' => 1,
    ]);

    // Korupsi manual: ubah balance tanpa ledger
    StockBalance::where('batch_id', $batch->id)->update(['qty_on_hand' => 50]);

    $issues = $this->writer->findInconsistencies();
    expect($issues)->toHaveCount(1);
    expect($issues[0]['ledger_sum'])->toBe(100);
    expect($issues[0]['balance'])->toBe(50);
});

test('qty_available = qty_on_hand - qty_reserved (computed)', function (): void {
    [$product, $batch] = ledgerFreshProduct();
    $baseUnit = $product->units->first();

    $this->writer->writeIn([
        'product_id' => $product->id, 'batch_id' => $batch->id, 'product_unit_id' => $baseUnit->id,
        'type' => StockLedger::TYPE_PURCHASE_IN, 'qty_in' => 100, 'cost_price' => 1000,
        'ref_type' => 'GRN', 'ref_id' => 1,
    ]);

    $balance = StockBalance::where('batch_id', $batch->id)->first();
    $balance->update(['qty_reserved' => 30]);

    expect($balance->fresh()->qty_available)->toBe(70);
});
