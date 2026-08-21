<?php

use App\Models\Product;
use App\Models\Role;
use App\Models\StockBalance;
use App\Models\StockLedger;
use App\Models\StockOpening;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\Inventory\StockOpeningService;
use App\Services\Product\ProductService;
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

function openingAdmin(): User
{
    return User::query()->whereHas('role', fn ($q) => $q->where('code', Role::CODE_SUPERADMIN))->firstOrFail();
}

/**
 * Produk baru TANPA stok & TANPA batch (skenario stok awal).
 */
function makeFreshProduct(): Product
{
    $admin = openingAdmin();

    $supplier = Supplier::create([
        'code' => 'SUP-OB', 'name' => 'Supplier OB', 'is_active' => true, 'created_by' => $admin->id,
    ]);

    $pcs = Unit::query()->where('name', 'PCS')->value('id');

    return app(ProductService::class)->create(
        ['supplier_id' => $supplier->id, 'sku' => 'OB-001', 'name' => 'Produk Baru', 'is_active' => true, 'created_by' => $admin->id],
        [['unit_id' => $pcs, 'qty_to_base' => 1, 'barcode' => null]],
        [['name' => 'Harga Reguler', 'items' => [['unit_index' => 0, 'cost_price' => 5000, 'sell_price' => 7000]]]],
    )->fresh(['units']);
}

test('stok awal untuk produk baru (belum berstok): posting menambah stok & buat batch', function (): void {
    $admin = openingAdmin();
    $product = makeFreshProduct();
    $pcsUnit = $product->units->firstWhere('qty_to_base', 1);

    // Sebelum: tidak ada stok & tidak ada batch.
    expect(StockBalance::query()->where('product_id', $product->id)->count())->toBe(0);
    expect($product->batches()->count())->toBe(0);

    $opening = app(StockOpeningService::class)->createDraft(
        ['opening_date' => now()->toDateString()],
        [['product_id' => $product->id, 'qty' => 100, 'cost_price' => 5000]],
        $admin,
    );
    expect($opening->status)->toBe(StockOpening::STATUS_DRAFT);
    // Batch dikosongkan → default "OPENING".
    expect($opening->items()->first()->batch_code)->toBe('OPENING');

    app(StockOpeningService::class)->post($opening, $admin);

    // Sesudah: stok = 100, batch terbuat, ledger opening_in tercatat.
    expect((int) StockBalance::query()->where('product_id', $product->id)->sum('qty_on_hand'))->toBe(100);
    expect($product->batches()->count())->toBe(1);
    expect(StockLedger::query()->where('product_id', $product->id)->where('type', StockLedger::TYPE_OPENING_IN)->exists())->toBeTrue();
    expect($opening->refresh()->status)->toBe(StockOpening::STATUS_POSTED);
    unset($pcsUnit);
});

test('stok awal via HTTP: operator input draft, admin posting', function (): void {
    $admin = openingAdmin();
    $product = makeFreshProduct();

    // Buat draft via HTTP.
    $this->actingAs($admin)->post(route('openings.store'), [
        'opening_date' => now()->toDateString(),
        'items' => [
            ['product_id' => $product->id, 'batch_code' => 'BATCH-A', 'expired_date' => now()->addYear()->toDateString(), 'qty' => 50, 'cost_price' => 5000],
        ],
    ])->assertRedirect();

    $opening = StockOpening::query()->latest('id')->firstOrFail();
    expect($opening->items()->first()->batch_code)->toBe('BATCH-A');

    // Posting via HTTP.
    $this->actingAs($admin)->post(route('openings.post', $opening->id))->assertRedirect();

    expect((int) StockBalance::query()->where('product_id', $product->id)->sum('qty_on_hand'))->toBe(50);
    // Batch dibuat dengan kode & expired sesuai input.
    $batch = $product->batches()->first();
    expect($batch->batch_code)->toBe('BATCH-A');
    expect($batch->expired_date)->not->toBeNull();
});

test('stok awal menolak qty <= 0', function (): void {
    $admin = openingAdmin();
    $product = makeFreshProduct();

    expect(fn () => app(StockOpeningService::class)->createDraft(
        ['opening_date' => now()->toDateString()],
        [['product_id' => $product->id, 'qty' => 0]],
        $admin,
    ))->toThrow(ValidationException::class);
});

test('stok awal draft bisa dibatalkan, stok tidak berubah', function (): void {
    $admin = openingAdmin();
    $product = makeFreshProduct();

    $opening = app(StockOpeningService::class)->createDraft(
        ['opening_date' => now()->toDateString()],
        [['product_id' => $product->id, 'qty' => 30]],
        $admin,
    );

    app(StockOpeningService::class)->cancel($opening, 'Salah input', $admin);

    expect($opening->refresh()->status)->toBe(StockOpening::STATUS_CANCELLED);
    // Belum diposting → stok tetap 0.
    expect((int) StockBalance::query()->where('product_id', $product->id)->sum('qty_on_hand'))->toBe(0);
});

test('stok awal pilih satuan besar (KRT): dikonversi ke base saat posting', function (): void {
    $admin = openingAdmin();
    $supplier = Supplier::create(['code' => 'SUP-K', 'name' => 'Sup K', 'is_active' => true, 'created_by' => $admin->id]);
    $pack = Unit::query()->where('name', 'PACK')->value('id') ?? Unit::query()->where('name', 'PCS')->value('id');
    $krt = Unit::query()->where('name', 'KARDUS')->value('id');

    // Produk: PACK base (=1), KARDUS ×12.
    $product = app(ProductService::class)->create(
        ['supplier_id' => $supplier->id, 'sku' => 'OB-KRT', 'name' => 'Produk KRT', 'is_active' => true, 'created_by' => $admin->id],
        [
            ['unit_id' => $pack, 'qty_to_base' => 1, 'barcode' => null],
            ['unit_id' => $krt, 'qty_to_base' => 12, 'barcode' => null],
        ],
        [['name' => 'Reg', 'items' => [
            ['unit_index' => 0, 'cost_price' => 1000, 'sell_price' => 1500],
            ['unit_index' => 1, 'cost_price' => 12000, 'sell_price' => 18000],
        ]]],
    )->fresh(['units']);

    $krtUnit = $product->units->firstWhere('qty_to_base', 12);

    // Input 10 KRT → harus jadi 120 base (PACK).
    $opening = app(StockOpeningService::class)->createDraft(
        ['opening_date' => now()->toDateString()],
        [['product_id' => $product->id, 'product_unit_id' => $krtUnit->id, 'qty' => 10, 'cost_price' => 12000]],
        $admin,
    );

    $item = $opening->items()->first();
    expect((int) $item->qty)->toBe(10);
    expect((int) $item->qty_base)->toBe(120); // 10 × 12
    expect($item->product_unit_name_snapshot)->toBe($krtUnit->name);

    app(StockOpeningService::class)->post($opening, $admin);

    // Stok masuk = 120 base (bukan 10).
    expect((int) StockBalance::query()->where('product_id', $product->id)->sum('qty_on_hand'))->toBe(120);
});

test('stok awal menolak satuan yang bukan milik produk', function (): void {
    $admin = openingAdmin();
    $product = makeFreshProduct();

    // Produk lain dengan SKU berbeda; ambil satuannya.
    $sup = Supplier::create(['code' => 'SUP-X', 'name' => 'Sup X', 'is_active' => true, 'created_by' => $admin->id]);
    $pcs = Unit::query()->where('name', 'PCS')->value('id');
    $other = app(ProductService::class)->create(
        ['supplier_id' => $sup->id, 'sku' => 'OB-OTHER', 'name' => 'Produk Lain', 'is_active' => true, 'created_by' => $admin->id],
        [['unit_id' => $pcs, 'qty_to_base' => 1, 'barcode' => null]],
        [['name' => 'Reg', 'items' => [['unit_index' => 0, 'cost_price' => 1000, 'sell_price' => 1500]]]],
    )->fresh(['units']);
    $otherUnit = $other->units->first();

    expect(fn () => app(StockOpeningService::class)->createDraft(
        ['opening_date' => now()->toDateString()],
        [['product_id' => $product->id, 'product_unit_id' => $otherUnit->id, 'qty' => 5]],
        $admin,
    ))->toThrow(ValidationException::class);
});

test('stok awal dengan bonus: reguler ke stok, bonus ke bonus_pool', function (): void {
    $admin = openingAdmin();
    $supplier = Supplier::create(['code' => 'SUP-B', 'name' => 'Sup B', 'is_active' => true, 'created_by' => $admin->id]);
    $pcs = Unit::query()->where('name', 'PCS')->value('id');

    $product = app(ProductService::class)->create(
        ['supplier_id' => $supplier->id, 'sku' => 'OB-BON', 'name' => 'Produk Bonus', 'is_active' => true, 'created_by' => $admin->id],
        [['unit_id' => $pcs, 'qty_to_base' => 1, 'barcode' => null]],
        [['name' => 'Reg', 'items' => [['unit_index' => 0, 'cost_price' => 1000, 'sell_price' => 1500]]]],
    )->fresh(['units']);
    $unit = $product->units->first();

    // 100 reguler + 5 bonus.
    $opening = app(StockOpeningService::class)->createDraft(
        ['opening_date' => now()->toDateString()],
        [['product_id' => $product->id, 'product_unit_id' => $unit->id, 'qty' => 100, 'qty_bonus' => 5, 'cost_price' => 1000]],
        $admin,
    );
    app(StockOpeningService::class)->post($opening, $admin);

    $bal = StockBalance::query()->where('product_id', $product->id)->first();
    // on_hand = reguler + bonus (bonus juga bagian on_hand), bonus_pool = 5.
    expect((int) $bal->qty_on_hand)->toBe(105);
    expect((int) $bal->qty_bonus_pool)->toBe(5);
    // Stok real (jual) = on_hand − bonus = 100.
    expect((int) $bal->qty_on_hand - (int) $bal->qty_bonus_pool)->toBe(100);
});
