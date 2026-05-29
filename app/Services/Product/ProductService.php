<?php

namespace App\Services\Product;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SupplierProduct;
use App\Models\Unit;
use App\Services\Numbering\NumberingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Orchestrator untuk Product CRUD.
 *
 * Flow create:
 *  - SKU auto-generate via NumberingService dgn context kategori
 *  - Insert product → insert N satuan (dinamis, level concept dihapus)
 *  - Tepat 1 satuan harus qty_to_base=1 (jadi base_unit_id)
 *  - Tag supplier(s) ke pivot supplier_products (wajib minimal 1)
 *  - Harga ditentukan setelah create lewat tab Supplier & Harga
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
     * @param  array<string, mixed>  $productData
     * @param  array<int, array{unit_id:int, qty_to_base:int, barcode?:?string}>  $units
     *                                                                                    Minimal 1 satuan, salah satunya wajib qty_to_base=1 (base).
     * @param  array<int, int>  $supplierIds  minimal 1 supplier wajib (tagging).
     */
    public function create(array $productData, array $units, array $supplierIds): Product
    {
        $this->uom->validateHierarchy($units);

        if (empty($supplierIds)) {
            throw new InvalidArgumentException('Minimal 1 supplier wajib di-tag ke produk.');
        }

        return DB::transaction(function () use ($productData, $units, $supplierIds): Product {
            $categoryCode = $this->resolveCategoryCode($productData['category_id'] ?? null);
            $productData['sku'] ??= $this->numbering->next('product_sku', ['cat' => $categoryCode]);
            $productData['is_active'] = $productData['is_active'] ?? true;

            unset($productData['brand']); // brand di-deprecate, ganti tagging supplier

            $product = Product::create($productData);

            $baseUnitId = null;
            foreach ($units as $i => $u) {
                $unitMasterName = Unit::query()->where('id', $u['unit_id'])->value('name') ?? 'UNIT';
                $created = $product->units()->create([
                    'unit_id' => $u['unit_id'],
                    'name' => $unitMasterName,
                    'qty_to_base' => (int) $u['qty_to_base'],
                    'barcode' => $u['barcode'] ?? null,
                    'sort_order' => $i,
                ]);

                if ((int) $u['qty_to_base'] === 1) {
                    $baseUnitId = $created->id;
                }
            }

            $product->base_unit_id = $baseUnitId;
            $product->save();

            // Tag suppliers — first one jadi primary
            $supplierIds = array_values(array_unique(array_map('intval', $supplierIds)));
            foreach ($supplierIds as $idx => $sid) {
                SupplierProduct::create([
                    'supplier_id' => $sid,
                    'product_id' => $product->id,
                    'is_primary' => $idx === 0,
                    'is_active' => true,
                ]);
            }

            $this->invalidateCache();

            return $product->fresh(['category', 'baseUnit', 'units', 'supplierProducts.supplier']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Product $product, array $data): Product
    {
        return DB::transaction(function () use ($product, $data): Product {
            unset($data['sku']); // SKU read-only setelah create
            unset($data['brand']); // brand deprecated
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
        $code = ProductCategory::query()->where('id', $categoryId)->value('code');

        return $code ?: 'LNY';
    }
}
