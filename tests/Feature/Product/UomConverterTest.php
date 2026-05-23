<?php

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductUnit;
use App\Services\Product\ProductService;
use App\Services\Product\UomConverter;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(\Database\Seeders\SettingSeeder::class);
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->seed(\Database\Seeders\ProductCategorySeeder::class);
    $this->seed(\Database\Seeders\PriceTierSeeder::class);

    $this->uom = app(UomConverter::class);
});

function freshProductWith3Units(): Product
{
    return app(ProductService::class)->create(
        [
            'name' => 'Indomie Karton',
            'category_id' => ProductCategory::where('code', 'MIE')->value('id'),
        ],
        [
            ['level' => ProductUnit::LEVEL_BSR, 'name' => 'Karton', 'qty_to_base' => 40],
            ['level' => ProductUnit::LEVEL_TGH, 'name' => 'Pak', 'qty_to_base' => 10],
            ['level' => ProductUnit::LEVEL_KCL, 'name' => 'Pcs', 'qty_to_base' => 1],
        ],
    );
}

test('toBase mengubah qty BSR menjadi base unit', function (): void {
    $product = freshProductWith3Units();
    $bsr = $product->units->firstWhere('level', 'BSR');

    expect($this->uom->toBase($bsr, 2))->toBe(80); // 2 karton × 40
});

test('fromBase mengubah base ke unit target (floor)', function (): void {
    $product = freshProductWith3Units();
    $tgh = $product->units->firstWhere('level', 'TGH');

    expect($this->uom->fromBase($tgh, 25))->toBe(2); // 25 ÷ 10 = 2 (floor)
});

test('formatBreakdown menghasilkan "10 Krt + 2 Pak + 4 Pcs" style', function (): void {
    $product = freshProductWith3Units();
    // 10 × 40 + 2 × 10 + 4 = 424
    $result = $this->uom->formatBreakdown($product, 424);
    expect($result)->toBe('10 Karton + 2 Pak + 4 Pcs');
});

test('formatBreakdown menampilkan unit yang dipakai saja', function (): void {
    $product = freshProductWith3Units();
    expect($this->uom->formatBreakdown($product, 5))->toBe('5 Pcs');
    expect($this->uom->formatBreakdown($product, 40))->toBe('1 Karton');
    expect($this->uom->formatBreakdown($product, 0))->toBe('0');
});

test('validateHierarchy reject KCL tanpa qty_to_base=1', function (): void {
    expect(fn () => $this->uom->validateHierarchy([
        ['level' => 'KCL', 'qty_to_base' => 2],
    ]))->toThrow(\InvalidArgumentException::class);
});

test('validateHierarchy reject TGH dengan qty_to_base ≤ 1', function (): void {
    expect(fn () => $this->uom->validateHierarchy([
        ['level' => 'TGH', 'qty_to_base' => 1],
        ['level' => 'KCL', 'qty_to_base' => 1],
    ]))->toThrow(\InvalidArgumentException::class);
});

test('validateHierarchy reject BSR ≤ TGH', function (): void {
    expect(fn () => $this->uom->validateHierarchy([
        ['level' => 'BSR', 'qty_to_base' => 10],
        ['level' => 'TGH', 'qty_to_base' => 10],
        ['level' => 'KCL', 'qty_to_base' => 1],
    ]))->toThrow(\InvalidArgumentException::class);
});

test('validateHierarchy accept BSR > TGH > KCL=1', function (): void {
    expect(fn () => $this->uom->validateHierarchy([
        ['level' => 'BSR', 'qty_to_base' => 40],
        ['level' => 'TGH', 'qty_to_base' => 10],
        ['level' => 'KCL', 'qty_to_base' => 1],
    ]))->not->toThrow(\InvalidArgumentException::class);
});
