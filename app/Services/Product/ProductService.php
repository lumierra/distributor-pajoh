<?php

namespace App\Services\Product;

use App\Models\PriceTier;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\ProductUnit;
use App\Services\Numbering\NumberingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Orchestrator untuk Product CRUD. Per blueprint T04:
 *  - SKU auto-generate via NumberingService dengan context kategori
 *  - Wizard create: insert product → insert KCL unit (wajib) + optional TGH/BSR →
 *    update base_unit_id ke KCL → auto-generate price matrix (semua kombinasi
 *    unit × tier dengan price=0)
 *  - Semua dalam DB::transaction
 *  - Cache `products:active` invalidate setelah mutasi
 */
class ProductService
{
    public const CACHE_KEY_ACTIVE = 'products:active';

    public const CACHE_TTL_SECONDS = 600;

    public function __construct(
        private readonly NumberingService $numbering,
        private readonly UomConverter $uom,
    ) {}

    /**
     * Create produk lengkap dengan unit + price matrix.
     *
     * @param  array<string, mixed>  $productData
     * @param  array<int, array{level:string, name:string, qty_to_base:int, barcode?:?string}>  $units
     *     Minimal harus ada 1 unit dengan level=KCL & qty_to_base=1.
     */
    public function create(array $productData, array $units): Product
    {
        $this->uom->validateHierarchy($units);

        return DB::transaction(function () use ($productData, $units): Product {
            // 1. Generate SKU dengan context kategori
            $categoryCode = $this->resolveCategoryCode($productData['category_id'] ?? null);
            $productData['sku'] ??= $this->numbering->next('product_sku', ['cat' => $categoryCode]);
            $productData['is_active'] = $productData['is_active'] ?? true;

            // 2. Insert product (tanpa base_unit_id dulu)
            $product = Product::create($productData);

            // 3. Insert units. Sort biar KCL pertama supaya base_unit_id pasti diset.
            $unitsCollection = collect($units)->sortBy(fn ($u) => match ($u['level']) {
                ProductUnit::LEVEL_KCL => 0,
                ProductUnit::LEVEL_TGH => 1,
                ProductUnit::LEVEL_BSR => 2,
                default => 99,
            });

            $kclUnit = null;
            $createdUnits = [];
            foreach ($unitsCollection as $i => $u) {
                $unit = $product->units()->create([
                    'level' => $u['level'],
                    'name' => $u['name'],
                    'qty_to_base' => (int) $u['qty_to_base'],
                    'barcode' => $u['barcode'] ?? null,
                    'sort_order' => $i,
                ]);
                if ($unit->level === ProductUnit::LEVEL_KCL) {
                    $kclUnit = $unit;
                }
                $createdUnits[] = $unit;
            }

            // 4. Update base_unit_id ke KCL
            $product->base_unit_id = $kclUnit->id;
            $product->save();

            // 5. Generate price matrix: unit × tier (semua price=0)
            $tiers = PriceTier::query()->active()->get();
            foreach ($createdUnits as $unit) {
                foreach ($tiers as $tier) {
                    ProductPrice::create([
                        'product_id' => $product->id,
                        'product_unit_id' => $unit->id,
                        'price_tier_id' => $tier->id,
                        'price' => 0,
                    ]);
                }
            }

            $this->invalidateCache();

            return $product->fresh(['category', 'baseUnit', 'units', 'prices.unit', 'prices.tier']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Product $product, array $data): Product
    {
        return DB::transaction(function () use ($product, $data): Product {
            unset($data['sku']); // SKU adalah read-only setelah create
            $product->update($data);
            $this->invalidateCache();

            return $product;
        });
    }

    public function toggleActive(Product $product): Product
    {
        $product->forceFill(['is_active' => ! $product->is_active])->save();
        $this->invalidateCache();

        return $product;
    }

    public function invalidateCache(): void
    {
        Cache::forget(self::CACHE_KEY_ACTIVE);
    }

    private function resolveCategoryCode(?int $categoryId): string
    {
        if ($categoryId === null) {
            return 'LNY';
        }
        $code = \App\Models\ProductCategory::query()->where('id', $categoryId)->value('code');

        return $code ?: 'LNY';
    }
}
