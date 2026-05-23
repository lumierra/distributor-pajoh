<?php

use App\Exceptions\InsufficientStockException;
use App\Models\ProductBatch;
use App\Models\ProductCategory;
use App\Models\ProductUnit;
use App\Models\StockBalance;
use App\Models\StockLedger;
use App\Services\Inventory\FifoPicker;
use App\Services\Inventory\StockLedgerWriter;
use App\Services\Product\ProductService;
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
    $this->picker = app(FifoPicker::class);
});

function fifoMakeProduct(): array
{
    $product = app(ProductService::class)->create(
        [
            'name' => 'Fifo Test '.random_int(100, 999),
            'category_id' => ProductCategory::where('code', 'MIE')->value('id'),
        ],
        [['level' => ProductUnit::LEVEL_KCL, 'name' => 'Pcs', 'qty_to_base' => 1]],
    );

    return [$product, $product->units->first()];
}

function seedBatchWithStock(
    int $productId,
    int $unitId,
    int $qty,
    ?string $expiredDate = null,
    ?string $createdAt = null,
): ProductBatch {
    $batch = ProductBatch::create([
        'product_id' => $productId,
        'batch_code' => 'B-'.random_int(10000, 99999),
        'expired_date' => $expiredDate,
        'is_active' => true,
        'created_at' => $createdAt ?: now(),
        'updated_at' => $createdAt ?: now(),
    ]);

    app(StockLedgerWriter::class)->writeIn([
        'product_id' => $productId, 'batch_id' => $batch->id, 'product_unit_id' => $unitId,
        'type' => StockLedger::TYPE_PURCHASE_IN, 'qty_in' => $qty, 'cost_price' => 1000,
        'ref_type' => 'GRN', 'ref_id' => 1,
    ]);

    return $batch;
}

test('pick urut by expired_date ASC', function (): void {
    [$product, $unit] = fifoMakeProduct();

    $batchLater = seedBatchWithStock($product->id, $unit->id, 50, now()->addMonths(6)->toDateString());
    $batchEarlier = seedBatchWithStock($product->id, $unit->id, 30, now()->addMonths(1)->toDateString());

    $picks = $this->picker->pick($product->id, 40);

    // Should pick batchEarlier first (30), then 10 from batchLater
    expect($picks[0]['batch_id'])->toBe($batchEarlier->id);
    expect($picks[0]['qty'])->toBe(30);
    expect($picks[1]['batch_id'])->toBe($batchLater->id);
    expect($picks[1]['qty'])->toBe(10);
});

test('pick skip batch yang sudah expired', function (): void {
    [$product, $unit] = fifoMakeProduct();

    seedBatchWithStock($product->id, $unit->id, 50, now()->subDays(5)->toDateString());
    $fresh = seedBatchWithStock($product->id, $unit->id, 20, now()->addMonths(3)->toDateString());

    $picks = $this->picker->pick($product->id, 20);

    expect($picks)->toHaveCount(1);
    expect($picks[0]['batch_id'])->toBe($fresh->id);
});

test('pick stok kurang throw InsufficientStockException', function (): void {
    [$product, $unit] = fifoMakeProduct();

    seedBatchWithStock($product->id, $unit->id, 10);

    expect(fn () => $this->picker->pick($product->id, 50))
        ->toThrow(InsufficientStockException::class);
});

test('pick NULL expired_date paling akhir', function (): void {
    [$product, $unit] = fifoMakeProduct();

    $batchNoExpired = seedBatchWithStock($product->id, $unit->id, 100, null);
    $batchExpDated = seedBatchWithStock($product->id, $unit->id, 50, now()->addMonths(2)->toDateString());

    $picks = $this->picker->pick($product->id, 40);

    expect($picks[0]['batch_id'])->toBe($batchExpDated->id);
});

test('pick respect created_at sebagai tie-breaker', function (): void {
    [$product, $unit] = fifoMakeProduct();

    // Sama expired_date, beda created_at
    $expired = now()->addMonths(3)->toDateString();
    $older = seedBatchWithStock($product->id, $unit->id, 50, $expired, now()->subDay()->toDateTimeString());
    $newer = seedBatchWithStock($product->id, $unit->id, 50, $expired, now()->toDateTimeString());

    $picks = $this->picker->pick($product->id, 30);
    expect($picks[0]['batch_id'])->toBe($older->id);
});

test('pick batch dengan qty_reserved tidak melebihi available', function (): void {
    [$product, $unit] = fifoMakeProduct();
    $batch = seedBatchWithStock($product->id, $unit->id, 100);

    StockBalance::where('batch_id', $batch->id)->update(['qty_reserved' => 60]);

    // Available = 100 - 60 = 40. Minta 30 → OK.
    $picks = $this->picker->pick($product->id, 30);
    expect($picks[0]['qty'])->toBe(30);

    // Minta 50 → InsufficientStock karena available cuma 40.
    expect(fn () => $this->picker->pick($product->id, 50))
        ->toThrow(InsufficientStockException::class);
});
