<?php

namespace App\Services\Product;

use App\Models\Product;
use App\Models\Unit;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Orchestrator untuk Product CRUD.
 *
 * Model: 1 produk = 1 supplier. SKU diinput manual (unik). Produk punya
 * satuan dinamis (product_units, konversi ke base) + ≥1 paket harga bernama
 * (product_price_packages), tiap paket berisi baris (satuan → modal + jual).
 *
 * Flow create:
 *  - Insert product (supplier_id + sku manual) → insert N satuan
 *  - Tepat 1 satuan qty_to_base=1 (jadi base_unit_id)
 *  - Insert ≥1 paket harga + baris-barisnya (product_unit_id → cost/sell)
 */
class ProductService
{
    public const CACHE_KEY_ACTIVE = 'products:active';

    public const CACHE_TTL_SECONDS = 600;

    public function __construct(
        private readonly UomConverter $uom,
    ) {}

    /**
     * @param  array<string, mixed>  $productData  wajib berisi supplier_id, sku, name.
     * @param  array<int, array{unit_id:int, qty_to_base:int, barcode?:?string}>  $units
     *                                                                                    Minimal 1 satuan, salah satunya wajib qty_to_base=1 (base).
     * @param  array<int, array{name:string, items:array<int, array{unit_index:int, cost_price:float, sell_price:float}>}>  $packages
     *                                                                                                                                 Minimal 1 paket harga. items[].unit_index menunjuk index di $units.
     */
    public function create(array $productData, array $units, array $packages): Product
    {
        $this->uom->validateHierarchy($units);
        $this->validatePackages($packages, count($units));

        return DB::transaction(function () use ($productData, $units, $packages): Product {
            $productData['is_active'] = $productData['is_active'] ?? true;

            $product = Product::create($productData);

            // Insert satuan; simpan mapping index → product_unit id untuk paket harga.
            $unitIdByIndex = [];
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
                $unitIdByIndex[$i] = $created->id;

                if ((int) $u['qty_to_base'] === 1) {
                    $baseUnitId = $created->id;
                }
            }

            $product->base_unit_id = $baseUnitId;
            $product->save();

            $this->syncPackages($product, $packages, $unitIdByIndex);

            $this->invalidateCache();

            return $product->fresh(['supplier', 'category', 'baseUnit', 'units', 'pricePackages.items']);
        });
    }

    /**
     * Update produk. Kalau $units/$packages disertakan, satuan & paket harga
     * di-replace penuh (hapus lama, insert baru) — dipakai form edit yang
     * mengirim ulang seluruh struktur. Kalau null, hanya kolom scalar produk
     * yang diupdate (mis. toggle sederhana).
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, array{unit_id:int, qty_to_base:int, barcode?:?string}>|null  $units
     * @param  array<int, array{name:string, items:array<int, array{unit_index:int, cost_price:float, sell_price:float}>}>|null  $packages
     */
    public function update(Product $product, array $data, ?array $units = null, ?array $packages = null): Product
    {
        return DB::transaction(function () use ($product, $data, $units, $packages): Product {
            $product->update($data);

            if ($units !== null && $packages !== null) {
                $this->uom->validateHierarchy($units);
                $this->validatePackages($packages, count($units));
                $this->replaceUnitsAndPackages($product, $units, $packages);
            }

            $this->invalidateCache();

            return $product->fresh(['supplier', 'category', 'baseUnit', 'units', 'pricePackages.items']);
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

    /**
     * Hapus semua satuan + paket lama lalu buat ulang. Aman untuk edit
     * karena item transaksi (so_items dst) menyimpan snapshot sendiri.
     *
     * @param  array<int, array{unit_id:int, qty_to_base:int, barcode?:?string}>  $units
     * @param  array<int, array{name:string, items:array<int, array{unit_index:int, cost_price:float, sell_price:float}>}>  $packages
     */
    private function replaceUnitsAndPackages(Product $product, array $units, array $packages): void
    {
        $product->pricePackages()->each(function ($pkg): void {
            $pkg->items()->delete();
            $pkg->delete();
        });
        $product->units()->delete();

        $unitIdByIndex = [];
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
            $unitIdByIndex[$i] = $created->id;
            if ((int) $u['qty_to_base'] === 1) {
                $baseUnitId = $created->id;
            }
        }

        $product->base_unit_id = $baseUnitId;
        $product->save();

        $this->syncPackages($product, $packages, $unitIdByIndex);
    }

    /**
     * @param  array<int, array{name:string, items:array<int, array{unit_index:int, cost_price:float, sell_price:float}>}>  $packages
     * @param  array<int, int>  $unitIdByIndex  index satuan (di form) → product_unit id
     */
    private function syncPackages(Product $product, array $packages, array $unitIdByIndex): void
    {
        foreach ($packages as $pi => $pkg) {
            $package = $product->pricePackages()->create([
                'name' => $pkg['name'],
                'sort_order' => $pi,
                'is_active' => true,
            ]);

            foreach ($pkg['items'] as $item) {
                $unitId = $unitIdByIndex[$item['unit_index']] ?? null;
                if ($unitId === null) {
                    throw new InvalidArgumentException('Baris paket harga menunjuk satuan yang tidak ada.');
                }
                $package->items()->create([
                    'product_unit_id' => $unitId,
                    'cost_price' => (float) $item['cost_price'],
                    'sell_price' => (float) $item['sell_price'],
                ]);
            }
        }
    }

    /**
     * @param  array<int, mixed>  $packages
     */
    private function validatePackages(array $packages, int $unitCount): void
    {
        if (empty($packages)) {
            throw new InvalidArgumentException('Minimal 1 paket harga wajib.');
        }

        foreach ($packages as $pkg) {
            if (empty($pkg['name'] ?? null)) {
                throw new InvalidArgumentException('Nama paket harga wajib diisi.');
            }
            if (empty($pkg['items'] ?? [])) {
                throw new InvalidArgumentException('Tiap paket harga wajib punya minimal 1 baris satuan.');
            }
            $seen = [];
            foreach ($pkg['items'] as $item) {
                $idx = $item['unit_index'] ?? null;
                if ($idx === null || $idx < 0 || $idx >= $unitCount) {
                    throw new InvalidArgumentException('Baris paket harga menunjuk satuan yang tidak valid.');
                }
                if (in_array($idx, $seen, true)) {
                    throw new InvalidArgumentException('Satuan duplikat dalam satu paket harga.');
                }
                $seen[] = $idx;
            }
        }
    }
}
