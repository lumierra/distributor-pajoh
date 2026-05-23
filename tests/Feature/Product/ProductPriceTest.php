<?php

use App\Models\PriceTier;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductPrice;
use App\Models\ProductPriceHistory;
use App\Models\Role;
use App\Models\User;
use App\Services\Product\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(\Database\Seeders\SettingSeeder::class);
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->seed(\Database\Seeders\MenuSeeder::class);
    $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    $this->seed(\Database\Seeders\ProductCategorySeeder::class);
    $this->seed(\Database\Seeders\PriceTierSeeder::class);

    $this->admin = User::create([
        'name' => 'Admin',
        'username' => 'admin_pp_'.uniqid('', true),
        'password' => 'secret1234',
        'role_id' => Role::ofCode(Role::CODE_ADMIN)->value('id'),
        'is_active' => true,
        'force_password_change' => false,
    ]);

    $this->product = app(ProductService::class)->create(
        ['name' => 'P-Price', 'category_id' => ProductCategory::where('code', 'FMC')->value('id')],
        [['level' => 'KCL', 'name' => 'Pcs', 'qty_to_base' => 1]],
    );
});

test('update prices via controller berhasil + history inserted', function (): void {
    $tierGrosir = PriceTier::where('code', PriceTier::CODE_GROSIR)->value('id');
    $tierEceran = PriceTier::where('code', PriceTier::CODE_ECERAN)->value('id');
    $unit = $this->product->units->first();

    $this->actingAs($this->admin)
        ->put(route('products.prices.update', $this->product->id), [
            'prices' => [
                ['product_unit_id' => $unit->id, 'price_tier_id' => $tierGrosir, 'price' => 2500],
                ['product_unit_id' => $unit->id, 'price_tier_id' => $tierEceran, 'price' => 3000],
            ],
        ])
        ->assertRedirect();

    $row = ProductPrice::where('product_id', $this->product->id)
        ->where('price_tier_id', $tierGrosir)->first();
    expect((float) $row->price)->toBe(2500.0);

    // History entry untuk 2 perubahan
    expect(ProductPriceHistory::where('product_id', $this->product->id)->count())->toBe(2);

    $history = ProductPriceHistory::where('product_id', $this->product->id)
        ->where('price_tier_id', $tierGrosir)->first();
    expect((float) $history->old_price)->toBe(0.0);
    expect((float) $history->new_price)->toBe(2500.0);
    expect($history->change_reason)->toBe(ProductPriceHistory::REASON_MANUAL);
    expect($history->changed_by)->toBe($this->admin->id);
});

test('update price ke nilai yang sama TIDAK insert history baru', function (): void {
    $tierId = PriceTier::where('code', PriceTier::CODE_GROSIR)->value('id');
    $unit = $this->product->units->first();

    // First update: 0 → 5000 (1 history)
    $this->actingAs($this->admin)
        ->put(route('products.prices.update', $this->product->id), [
            'prices' => [
                ['product_unit_id' => $unit->id, 'price_tier_id' => $tierId, 'price' => 5000],
            ],
        ]);

    expect(ProductPriceHistory::where('product_id', $this->product->id)->count())->toBe(1);

    // Second update: 5000 → 5000 (TIDAK menambah history)
    $this->actingAs($this->admin)
        ->put(route('products.prices.update', $this->product->id), [
            'prices' => [
                ['product_unit_id' => $unit->id, 'price_tier_id' => $tierId, 'price' => 5000],
            ],
        ]);

    expect(ProductPriceHistory::where('product_id', $this->product->id)->count())->toBe(1);
});

test('tambah unit baru ke produk existing otomatis generate price 0 untuk semua tier', function (): void {
    $tiers = PriceTier::query()->active()->count();
    $existingPrices = ProductPrice::where('product_id', $this->product->id)->count();

    $this->actingAs($this->admin)
        ->post(route('products.units.store', $this->product->id), [
            'level' => 'BSR',
            'name' => 'Karton',
            'qty_to_base' => 40,
        ])
        ->assertRedirect();

    $newPrices = ProductPrice::where('product_id', $this->product->id)->count();
    expect($newPrices)->toBe($existingPrices + $tiers);
});

test('barcode unique cross-product', function (): void {
    $this->actingAs($this->admin)
        ->post(route('products.units.store', $this->product->id), [
            'level' => 'BSR',
            'name' => 'Krt',
            'qty_to_base' => 40,
            'barcode' => 'BC-12345',
        ])->assertRedirect();

    // Create another product
    $product2 = app(ProductService::class)->create(
        ['name' => 'P2', 'category_id' => ProductCategory::where('code', 'FMC')->value('id')],
        [['level' => 'KCL', 'name' => 'Pcs', 'qty_to_base' => 1]],
    );

    $this->actingAs($this->admin)
        ->post(route('products.units.store', $product2->id), [
            'level' => 'BSR',
            'name' => 'Krt',
            'qty_to_base' => 40,
            'barcode' => 'BC-12345', // duplicate
        ])->assertSessionHasErrors('barcode');
});

test('unit KCL tidak bisa dihapus', function (): void {
    $kcl = $this->product->units->first();
    expect($kcl->level)->toBe('KCL');

    $this->actingAs($this->admin)
        ->delete(route('product-units.destroy', $kcl->id))
        ->assertSessionHasErrors('delete');
});
