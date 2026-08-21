<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\Product\ProductService;
use Illuminate\Database\Seeder;

/**
 * Tambahan ~30 produk contoh, melengkapi DummyDataSeeder (6 produk awal) agar
 * semua kategori & supplier yang sudah ada punya isi. Aman dijalankan ulang —
 * produk dilewati kalau SKU sudah ada.
 *
 * Jalankan manual: php artisan db:seed --class=DummyProductBatch2Seeder
 */
class DummyProductBatch2Seeder extends Seeder
{
    private ?int $actingUserId = null;

    public function run(): void
    {
        $admin = User::query()->whereHas('role', fn ($q) => $q->where('code', Role::CODE_SUPERADMIN))->first();
        $this->actingUserId = $admin?->id;

        $unitId = fn (string $name) => Unit::query()->where('name', $name)->value('id');
        $categoryId = fn (string $code) => ProductCategory::query()->where('code', $code)->value('id');
        $supplierByCode = Supplier::query()->whereIn('code', ['SUP-0001', 'SUP-0002', 'SUP-0003', 'SUP-0004'])
            ->get()->keyBy('code');

        $service = app(ProductService::class);
        $created = 0;
        $skipped = 0;

        foreach ($this->specs() as $spec) {
            if (Product::query()->where('sku', $spec['sku'])->exists()) {
                $skipped++;

                continue;
            }

            $supplier = $supplierByCode->get($spec['supplier']);
            if (! $supplier) {
                continue;
            }

            $units = array_map(fn ($u) => [
                'unit_id' => $unitId($u['unit']),
                'qty_to_base' => $u['qty_to_base'],
                'barcode' => null,
            ], $spec['units']);

            $service->create(
                [
                    'supplier_id' => $supplier->id,
                    'sku' => $spec['sku'],
                    'name' => $spec['name'],
                    'category_id' => $categoryId($spec['category']),
                    'description' => null,
                    'is_active' => true,
                    'created_by' => $this->actingUserId,
                    'updated_by' => $this->actingUserId,
                ],
                $units,
                $spec['packages'],
            );
            $created++;
        }

        $this->command?->info("Batch 2 produk selesai: {$created} dibuat, {$skipped} sudah ada (dilewati).");
    }

    /**
     * Helper: paket "Harga Reguler" (wajib) + opsional "Harga Grosir" pada
     * satuan besar (index 1), dgn harga grosir ~5% lebih murah dari reguler.
     *
     * @param  array<int, array{unit_index:int, cost_price:int, sell_price:int}>  $regularItems
     */
    private function packages(array $regularItems, bool $withGrosir = false): array
    {
        $packages = [
            ['name' => 'Harga Reguler', 'items' => $regularItems],
        ];

        if ($withGrosir) {
            $bulk = collect($regularItems)->firstWhere('unit_index', 1);
            if ($bulk) {
                $packages[] = [
                    'name' => 'Harga Grosir',
                    'items' => [[
                        'unit_index' => 1,
                        'cost_price' => (int) round($bulk['cost_price'] * 0.97),
                        'sell_price' => (int) round($bulk['sell_price'] * 0.93),
                    ]],
                ];
            }
        }

        return $packages;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function specs(): array
    {
        return [
            // ── Mie & Pasta (Indofood) ──
            [
                'sku' => 'IDF-003', 'name' => 'Indomie Soto', 'supplier' => 'SUP-0001', 'category' => 'MIE',
                'units' => [['unit' => 'PCS', 'qty_to_base' => 1], ['unit' => 'KARDUS', 'qty_to_base' => 40]],
                'packages' => $this->packages([
                    ['unit_index' => 0, 'cost_price' => 2_750, 'sell_price' => 3_150],
                    ['unit_index' => 1, 'cost_price' => 106_000, 'sell_price' => 121_000],
                ], true),
            ],
            [
                'sku' => 'IDF-004', 'name' => 'Indomie Kari Ayam', 'supplier' => 'SUP-0001', 'category' => 'MIE',
                'units' => [['unit' => 'PCS', 'qty_to_base' => 1], ['unit' => 'KARDUS', 'qty_to_base' => 40]],
                'packages' => $this->packages([
                    ['unit_index' => 0, 'cost_price' => 2_800, 'sell_price' => 3_200],
                    ['unit_index' => 1, 'cost_price' => 108_000, 'sell_price' => 124_000],
                ]),
            ],
            [
                'sku' => 'IDF-005', 'name' => 'Supermi Ayam Bawang', 'supplier' => 'SUP-0001', 'category' => 'MIE',
                'units' => [['unit' => 'PCS', 'qty_to_base' => 1], ['unit' => 'KARDUS', 'qty_to_base' => 40]],
                'packages' => $this->packages([
                    ['unit_index' => 0, 'cost_price' => 2_400, 'sell_price' => 2_800],
                    ['unit_index' => 1, 'cost_price' => 92_000, 'sell_price' => 106_000],
                ], true),
            ],
            [
                'sku' => 'IDF-006', 'name' => 'Sarimi Isi 2 Ayam Bawang', 'supplier' => 'SUP-0001', 'category' => 'MIE',
                'units' => [['unit' => 'PCS', 'qty_to_base' => 1], ['unit' => 'KARDUS', 'qty_to_base' => 24]],
                'packages' => $this->packages([
                    ['unit_index' => 0, 'cost_price' => 3_600, 'sell_price' => 4_100],
                    ['unit_index' => 1, 'cost_price' => 84_000, 'sell_price' => 96_000],
                ]),
            ],
            [
                'sku' => 'IDF-007', 'name' => 'Indomie Rasa Kaldu Ayam', 'supplier' => 'SUP-0001', 'category' => 'MIE',
                'units' => [['unit' => 'PCS', 'qty_to_base' => 1], ['unit' => 'KARDUS', 'qty_to_base' => 40]],
                'packages' => $this->packages([
                    ['unit_index' => 0, 'cost_price' => 2_700, 'sell_price' => 3_100],
                    ['unit_index' => 1, 'cost_price' => 104_000, 'sell_price' => 118_000],
                ], true),
            ],

            // ── Bumbu & Bahan Pokok (Indofood) ──
            [
                'sku' => 'IDF-008', 'name' => 'Bimoli Minyak Goreng 1L', 'supplier' => 'SUP-0001', 'category' => 'BMB',
                'units' => [['unit' => 'PCS', 'qty_to_base' => 1], ['unit' => 'KRAT', 'qty_to_base' => 12]],
                'packages' => $this->packages([
                    ['unit_index' => 0, 'cost_price' => 16_500, 'sell_price' => 18_500],
                    ['unit_index' => 1, 'cost_price' => 195_000, 'sell_price' => 219_000],
                ], true),
            ],
            [
                'sku' => 'IDF-009', 'name' => 'Bumbu Racik Indofood Rendang', 'supplier' => 'SUP-0001', 'category' => 'BMB',
                'units' => [['unit' => 'PCS', 'qty_to_base' => 1], ['unit' => 'LUSIN', 'qty_to_base' => 12]],
                'packages' => $this->packages([
                    ['unit_index' => 0, 'cost_price' => 2_200, 'sell_price' => 2_800],
                    ['unit_index' => 1, 'cost_price' => 25_000, 'sell_price' => 32_000],
                ]),
            ],
            [
                'sku' => 'IDF-010', 'name' => 'Kecap Manis Indofood 625ml', 'supplier' => 'SUP-0001', 'category' => 'BMB',
                'units' => [['unit' => 'PCS', 'qty_to_base' => 1], ['unit' => 'KRAT', 'qty_to_base' => 12]],
                'packages' => $this->packages([
                    ['unit_index' => 0, 'cost_price' => 12_000, 'sell_price' => 14_000],
                    ['unit_index' => 1, 'cost_price' => 140_000, 'sell_price' => 162_000],
                ], true),
            ],
            [
                'sku' => 'IDF-011', 'name' => 'Tepung Bumbu Sasa Serbaguna', 'supplier' => 'SUP-0001', 'category' => 'BMB',
                'units' => [['unit' => 'PCS', 'qty_to_base' => 1], ['unit' => 'SAK', 'qty_to_base' => 20]],
                'packages' => $this->packages([
                    ['unit_index' => 0, 'cost_price' => 7_500, 'sell_price' => 8_800],
                    ['unit_index' => 1, 'cost_price' => 145_000, 'sell_price' => 168_000],
                ]),
            ],

            // ── Snack & Kembang Gula (Mayora) ──
            [
                'sku' => 'MYR-003', 'name' => 'Beng-Beng', 'supplier' => 'SUP-0002', 'category' => 'SNK',
                'units' => [['unit' => 'PCS', 'qty_to_base' => 1], ['unit' => 'BOX', 'qty_to_base' => 24]],
                'packages' => $this->packages([
                    ['unit_index' => 0, 'cost_price' => 1_500, 'sell_price' => 2_000],
                    ['unit_index' => 1, 'cost_price' => 33_000, 'sell_price' => 44_000],
                ], true),
            ],
            [
                'sku' => 'MYR-004', 'name' => 'Astor Wafer Roll Coklat', 'supplier' => 'SUP-0002', 'category' => 'SNK',
                'units' => [['unit' => 'PCS', 'qty_to_base' => 1], ['unit' => 'LUSIN', 'qty_to_base' => 12]],
                'packages' => $this->packages([
                    ['unit_index' => 0, 'cost_price' => 7_000, 'sell_price' => 8_200],
                    ['unit_index' => 1, 'cost_price' => 80_000, 'sell_price' => 94_000],
                ]),
            ],
            [
                'sku' => 'MYR-005', 'name' => 'Kopiko 78°C Coffee Drink', 'supplier' => 'SUP-0002', 'category' => 'MIN',
                'units' => [['unit' => 'PCS', 'qty_to_base' => 1], ['unit' => 'KRAT', 'qty_to_base' => 24]],
                'packages' => $this->packages([
                    ['unit_index' => 0, 'cost_price' => 4_200, 'sell_price' => 5_000],
                    ['unit_index' => 1, 'cost_price' => 98_000, 'sell_price' => 116_000],
                ], true),
            ],
            [
                'sku' => 'MYR-006', 'name' => 'Torabika Duo Instant Coffee', 'supplier' => 'SUP-0002', 'category' => 'MIN',
                'units' => [['unit' => 'PCS', 'qty_to_base' => 1], ['unit' => 'SAK', 'qty_to_base' => 10]],
                'packages' => $this->packages([
                    ['unit_index' => 0, 'cost_price' => 1_400, 'sell_price' => 1_800],
                    ['unit_index' => 1, 'cost_price' => 13_000, 'sell_price' => 17_000],
                ]),
            ],
            [
                'sku' => 'MYR-007', 'name' => 'Danone Energen Coklat', 'supplier' => 'SUP-0002', 'category' => 'MIN',
                'units' => [['unit' => 'PCS', 'qty_to_base' => 1], ['unit' => 'SAK', 'qty_to_base' => 20]],
                'packages' => $this->packages([
                    ['unit_index' => 0, 'cost_price' => 1_900, 'sell_price' => 2_400],
                    ['unit_index' => 1, 'cost_price' => 36_000, 'sell_price' => 46_000],
                ], true),
            ],
            [
                'sku' => 'MYR-008', 'name' => 'Choki-Choki Coklat Pasta', 'supplier' => 'SUP-0002', 'category' => 'SNK',
                'units' => [['unit' => 'PCS', 'qty_to_base' => 1], ['unit' => 'BOX', 'qty_to_base' => 20]],
                'packages' => $this->packages([
                    ['unit_index' => 0, 'cost_price' => 1_000, 'sell_price' => 1_500],
                    ['unit_index' => 1, 'cost_price' => 19_000, 'sell_price' => 28_000],
                ]),
            ],
            [
                'sku' => 'MYR-009', 'name' => 'Slai O\'lai Selai Sandwich', 'supplier' => 'SUP-0002', 'category' => 'SNK',
                'units' => [['unit' => 'PCS', 'qty_to_base' => 1], ['unit' => 'LUSIN', 'qty_to_base' => 12]],
                'packages' => $this->packages([
                    ['unit_index' => 0, 'cost_price' => 5_800, 'sell_price' => 6_800],
                    ['unit_index' => 1, 'cost_price' => 66_000, 'sell_price' => 78_000],
                ], true),
            ],

            // ── Snack (Garudafood) ──
            [
                'sku' => 'GRD-002', 'name' => 'Garuda Kacang Atom Pedas', 'supplier' => 'SUP-0003', 'category' => 'SNK',
                'units' => [['unit' => 'PCS', 'qty_to_base' => 1], ['unit' => 'KARDUS', 'qty_to_base' => 20]],
                'packages' => $this->packages([
                    ['unit_index' => 0, 'cost_price' => 5_500, 'sell_price' => 6_800],
                    ['unit_index' => 1, 'cost_price' => 106_000, 'sell_price' => 128_000],
                ], true),
            ],
            [
                'sku' => 'GRD-003', 'name' => 'Gery Saluut Coklat', 'supplier' => 'SUP-0003', 'category' => 'SNK',
                'units' => [['unit' => 'PCS', 'qty_to_base' => 1], ['unit' => 'LUSIN', 'qty_to_base' => 12]],
                'packages' => $this->packages([
                    ['unit_index' => 0, 'cost_price' => 4_500, 'sell_price' => 5_500],
                    ['unit_index' => 1, 'cost_price' => 51_000, 'sell_price' => 62_000],
                ]),
            ],
            [
                'sku' => 'GRD-004', 'name' => 'Leo Snack Rasa Ayam', 'supplier' => 'SUP-0003', 'category' => 'SNK',
                'units' => [['unit' => 'PCS', 'qty_to_base' => 1], ['unit' => 'KARDUS', 'qty_to_base' => 20]],
                'packages' => $this->packages([
                    ['unit_index' => 0, 'cost_price' => 3_800, 'sell_price' => 4_600],
                    ['unit_index' => 1, 'cost_price' => 73_000, 'sell_price' => 88_000],
                ], true),
            ],
            [
                'sku' => 'GRD-005', 'name' => 'Gudang Garam Klobot Kacang Telur', 'supplier' => 'SUP-0003', 'category' => 'SNK',
                'units' => [['unit' => 'PCS', 'qty_to_base' => 1], ['unit' => 'KARDUS', 'qty_to_base' => 24]],
                'packages' => $this->packages([
                    ['unit_index' => 0, 'cost_price' => 6_200, 'sell_price' => 7_400],
                    ['unit_index' => 1, 'cost_price' => 142_000, 'sell_price' => 170_000],
                ]),
            ],
            [
                'sku' => 'GRD-006', 'name' => 'Prochiz Keju Cheddar Slice', 'supplier' => 'SUP-0003', 'category' => 'BMB',
                'units' => [['unit' => 'PCS', 'qty_to_base' => 1], ['unit' => 'BOX', 'qty_to_base' => 24]],
                'packages' => $this->packages([
                    ['unit_index' => 0, 'cost_price' => 9_500, 'sell_price' => 11_000],
                    ['unit_index' => 1, 'cost_price' => 218_000, 'sell_price' => 252_000],
                ], true),
            ],
            [
                'sku' => 'GRD-007', 'name' => 'Okky Jelly Drink Leci', 'supplier' => 'SUP-0003', 'category' => 'MIN',
                'units' => [['unit' => 'PCS', 'qty_to_base' => 1], ['unit' => 'KRAT', 'qty_to_base' => 24]],
                'packages' => $this->packages([
                    ['unit_index' => 0, 'cost_price' => 2_100, 'sell_price' => 2_600],
                    ['unit_index' => 1, 'cost_price' => 48_000, 'sell_price' => 60_000],
                ]),
            ],

            // ── Personal Care & Rumah Tangga (Unilever) ──
            [
                'sku' => 'ULV-002', 'name' => 'Pepsodent Pasta Gigi 190gr', 'supplier' => 'SUP-0004', 'category' => 'PRC',
                'units' => [['unit' => 'PCS', 'qty_to_base' => 1], ['unit' => 'LUSIN', 'qty_to_base' => 12]],
                'packages' => $this->packages([
                    ['unit_index' => 0, 'cost_price' => 8_500, 'sell_price' => 10_000],
                    ['unit_index' => 1, 'cost_price' => 97_000, 'sell_price' => 114_000],
                ], true),
            ],
            [
                'sku' => 'ULV-003', 'name' => 'Sunsilk Shampoo 170ml', 'supplier' => 'SUP-0004', 'category' => 'PRC',
                'units' => [['unit' => 'PCS', 'qty_to_base' => 1], ['unit' => 'LUSIN', 'qty_to_base' => 12]],
                'packages' => $this->packages([
                    ['unit_index' => 0, 'cost_price' => 13_500, 'sell_price' => 15_800],
                    ['unit_index' => 1, 'cost_price' => 155_000, 'sell_price' => 182_000],
                ]),
            ],
            [
                'sku' => 'ULV-004', 'name' => 'Rinso Deterjen Bubuk 800gr', 'supplier' => 'SUP-0004', 'category' => 'RMT',
                'units' => [['unit' => 'PCS', 'qty_to_base' => 1], ['unit' => 'SAK', 'qty_to_base' => 12]],
                'packages' => $this->packages([
                    ['unit_index' => 0, 'cost_price' => 13_000, 'sell_price' => 15_500],
                    ['unit_index' => 1, 'cost_price' => 150_000, 'sell_price' => 178_000],
                ], true),
            ],
            [
                'sku' => 'ULV-005', 'name' => 'Sunlight Sabun Cuci Piring 755ml', 'supplier' => 'SUP-0004', 'category' => 'RMT',
                'units' => [['unit' => 'PCS', 'qty_to_base' => 1], ['unit' => 'KRAT', 'qty_to_base' => 12]],
                'packages' => $this->packages([
                    ['unit_index' => 0, 'cost_price' => 8_800, 'sell_price' => 10_500],
                    ['unit_index' => 1, 'cost_price' => 101_000, 'sell_price' => 120_000],
                ]),
            ],
            [
                'sku' => 'ULV-006', 'name' => 'Molto Pewangi Pakaian 900ml', 'supplier' => 'SUP-0004', 'category' => 'RMT',
                'units' => [['unit' => 'PCS', 'qty_to_base' => 1], ['unit' => 'KRAT', 'qty_to_base' => 12]],
                'packages' => $this->packages([
                    ['unit_index' => 0, 'cost_price' => 12_500, 'sell_price' => 14_800],
                    ['unit_index' => 1, 'cost_price' => 144_000, 'sell_price' => 170_000],
                ], true),
            ],
            [
                'sku' => 'ULV-007', 'name' => 'Clear Shampoo Men Cool Sport', 'supplier' => 'SUP-0004', 'category' => 'PRC',
                'units' => [['unit' => 'PCS', 'qty_to_base' => 1], ['unit' => 'LUSIN', 'qty_to_base' => 12]],
                'packages' => $this->packages([
                    ['unit_index' => 0, 'cost_price' => 14_000, 'sell_price' => 16_500],
                    ['unit_index' => 1, 'cost_price' => 160_000, 'sell_price' => 188_000],
                ]),
            ],
            [
                'sku' => 'ULV-008', 'name' => 'Wipol Karbol Wangi 780ml', 'supplier' => 'SUP-0004', 'category' => 'RMT',
                'units' => [['unit' => 'PCS', 'qty_to_base' => 1], ['unit' => 'KRAT', 'qty_to_base' => 12]],
                'packages' => $this->packages([
                    ['unit_index' => 0, 'cost_price' => 7_200, 'sell_price' => 8_600],
                    ['unit_index' => 1, 'cost_price' => 83_000, 'sell_price' => 99_000],
                ], true),
            ],
            [
                'sku' => 'ULV-009', 'name' => 'Vaseline Body Lotion 100ml', 'supplier' => 'SUP-0004', 'category' => 'PRC',
                'units' => [['unit' => 'PCS', 'qty_to_base' => 1], ['unit' => 'LUSIN', 'qty_to_base' => 12]],
                'packages' => $this->packages([
                    ['unit_index' => 0, 'cost_price' => 10_500, 'sell_price' => 12_500],
                    ['unit_index' => 1, 'cost_price' => 120_000, 'sell_price' => 142_000],
                ]),
            ],

            // ── FMCG umum & Lainnya (campur supplier) ──
            [
                'sku' => 'FMC-001', 'name' => 'Aqua Air Mineral 600ml', 'supplier' => 'SUP-0001', 'category' => 'FMC',
                'units' => [['unit' => 'PCS', 'qty_to_base' => 1], ['unit' => 'KRAT', 'qty_to_base' => 24]],
                'packages' => $this->packages([
                    ['unit_index' => 0, 'cost_price' => 2_500, 'sell_price' => 3_000],
                    ['unit_index' => 1, 'cost_price' => 57_000, 'sell_price' => 68_000],
                ], true),
            ],
            [
                'sku' => 'FMC-002', 'name' => 'Teh Pucuk Harum 350ml', 'supplier' => 'SUP-0002', 'category' => 'MIN',
                'units' => [['unit' => 'PCS', 'qty_to_base' => 1], ['unit' => 'KRAT', 'qty_to_base' => 24]],
                'packages' => $this->packages([
                    ['unit_index' => 0, 'cost_price' => 2_800, 'sell_price' => 3_400],
                    ['unit_index' => 1, 'cost_price' => 64_000, 'sell_price' => 78_000],
                ]),
            ],
            [
                'sku' => 'LNY-001', 'name' => 'Korek Api Gas Isi Ulang', 'supplier' => 'SUP-0003', 'category' => 'LNY',
                'units' => [['unit' => 'PCS', 'qty_to_base' => 1], ['unit' => 'LUSIN', 'qty_to_base' => 12]],
                'packages' => $this->packages([
                    ['unit_index' => 0, 'cost_price' => 3_000, 'sell_price' => 4_000],
                    ['unit_index' => 1, 'cost_price' => 34_000, 'sell_price' => 45_000],
                ], true),
            ],
            [
                'sku' => 'LNY-002', 'name' => 'Baterai ABC Alkaline AA', 'supplier' => 'SUP-0004', 'category' => 'LNY',
                'units' => [['unit' => 'PCS', 'qty_to_base' => 1], ['unit' => 'LUSIN', 'qty_to_base' => 12]],
                'packages' => $this->packages([
                    ['unit_index' => 0, 'cost_price' => 4_500, 'sell_price' => 5_800],
                    ['unit_index' => 1, 'cost_price' => 51_000, 'sell_price' => 65_000],
                ]),
            ],
        ];
    }
}
