<?php

namespace App\Console\Commands;

use App\Models\ProductCategory;
use App\Models\Supplier;
use App\Models\Unit;
use App\Services\Product\ProductService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Import master barang dari file "Database Ananda.xlsx" (sistem lama CV. Ananda).
 *
 * Kolom Excel (header di baris 7, data mulai baris 8):
 *   No | Kode | Nama Barang | Pabrik | Kategori | Jenis |
 *   Unit 1 | Unit 2 | Unit 3 | Stok 1 | Modal 1 | Harga 1 |
 *   Stok 2 | Modal 2 | Harga 2 | Stok 3 | Modal 3 | Harga 3 |
 *   Tgl Beli Akhir | Tgl Jual Akhir | Aktif
 *
 * Pemetaan:
 *   Pabrik      → suppliers.name (dibuat unik)
 *   Kode        → products.sku
 *   Nama Barang → products.name
 *   Kategori    → product_categories (dibuat), products.category_id
 *   Unit 1/2/3  → product_units (dedup kalau sama), master units dibuat kalau belum ada
 *   qty_to_base → dihitung dari rasio harga (Harga besar / Harga terkecil), dibulatkan
 *   Modal/Harga → price package "Harga Reguler" (cost_price / sell_price per satuan)
 *   Aktif       → is_active ("AKTIF" = true, "PASIF ..." = false)
 *
 * Stok TIDAK diimport (masuk lewat Stok Awal / Opening terpisah).
 */
class ImportAnandaProducts extends Command
{
    protected $signature = 'import:ananda
        {file : Path ke Database Ananda.xlsx}
        {--fresh : Truncate supplier/produk + turunannya & transaksi terkait dulu}
        {--dry-run : Baca & tampilkan ringkasan tanpa menulis ke database}';

    protected $description = 'Import master barang dari Database Ananda.xlsx';

    public function __construct(private readonly ProductService $products)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $file = $this->argument('file');
        if (! is_file($file)) {
            $this->error("File tidak ditemukan: {$file}");

            return self::FAILURE;
        }

        $this->info('Membaca Excel…');
        $rows = $this->readRows($file);
        $this->info('Baris data terbaca: '.count($rows));

        $parsed = $this->parseRows($rows);
        $this->line("Valid: {$parsed['valid_count']}  |  Dilewati: {$parsed['skipped_count']}");
        $this->line('Supplier unik: '.count($parsed['suppliers']).'  |  Kategori unik: '.count($parsed['categories']).'  |  Satuan unik: '.count($parsed['units']));

        if (! empty($parsed['warnings'])) {
            $this->warn('Catatan ('.count($parsed['warnings']).' — konversi tak bulat, dibulatkan):');
            foreach (array_slice($parsed['warnings'], 0, 10) as $w) {
                $this->line('  - '.$w);
            }
        }

        if ($this->option('dry-run')) {
            $this->info('DRY RUN — tidak ada yang ditulis.');

            return self::SUCCESS;
        }

        $userId = 1; // superadmin

        if ($this->option('fresh')) {
            $this->warn('Reset tabel supplier/produk + transaksi terkait…');
            $this->freshReset();
        }

        $this->info('Import…');
        $bar = $this->output->createProgressBar($parsed['valid_count']);

        // Master units & categories & suppliers dulu (unik).
        $unitIdByName = $this->ensureUnits($parsed['units'], $userId);
        $catIdByName = $this->ensureCategories($parsed['categories'], $userId);
        $supplierIdByName = $this->ensureSuppliers($parsed['suppliers'], $userId);

        $imported = 0;
        $failed = [];
        foreach ($parsed['products'] as $p) {
            try {
                $this->importProduct($p, $unitIdByName, $catIdByName, $supplierIdByName, $userId);
                $imported++;
            } catch (\Throwable $e) {
                $failed[] = $p['sku'].' — '.$e->getMessage();
            }
            $bar->advance();
        }
        $bar->finish();
        $this->newLine(2);

        $this->info("Selesai. Produk masuk: {$imported}");
        $this->line('Supplier: '.count($supplierIdByName).'  |  Kategori: '.count($catIdByName).'  |  Satuan master: '.count($unitIdByName));
        if ($failed) {
            $this->error('Gagal '.count($failed).':');
            foreach (array_slice($failed, 0, 15) as $f) {
                $this->line('  - '.$f);
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function readRows(string $file): array
    {
        $reader = IOFactory::createReaderForFile($file);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($file);
        $sheet = $spreadsheet->getSheetByName('BARANG') ?? $spreadsheet->getActiveSheet();
        $all = $sheet->toArray(null, true, false, false);

        // Data mulai baris 8 (index 7). Header di baris 7 (index 6).
        return array_slice($all, 7);
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function parseRows(array $rows): array
    {
        $products = [];
        $suppliers = [];
        $categories = [];
        $units = [];
        $warnings = [];
        $skipped = 0;

        foreach ($rows as $r) {
            $kode = trim((string) ($r[1] ?? ''));
            $nama = trim((string) ($r[2] ?? ''));

            // Skip baris tanpa kode/nama, atau data uji ngawur (nama < 3 char).
            if ($kode === '' || $nama === '' || strlen($nama) < 3) {
                $skipped++;

                continue;
            }

            $pabrik = trim((string) ($r[3] ?? ''));
            $kategori = trim((string) ($r[4] ?? ''));
            $u1 = strtoupper(trim((string) ($r[6] ?? '')));
            $u2 = strtoupper(trim((string) ($r[7] ?? '')));
            $u3 = strtoupper(trim((string) ($r[8] ?? '')));
            $modal1 = $this->num($r[10] ?? 0);
            $harga1 = $this->num($r[11] ?? 0);
            $modal2 = $this->num($r[13] ?? 0);
            $harga2 = $this->num($r[14] ?? 0);
            $modal3 = $this->num($r[16] ?? 0);
            $harga3 = $this->num($r[17] ?? 0);

            // Susun satuan efektif (dedup Unit2==Unit3, buang kosong).
            $rawUnits = [];
            $rawUnits[] = ['name' => $u1, 'modal' => $modal1, 'harga' => $harga1];
            if ($u2 !== '' && $u2 !== $u1) {
                $rawUnits[] = ['name' => $u2, 'modal' => $modal2, 'harga' => $harga2];
            }
            if ($u3 !== '' && $u3 !== $u2 && $u3 !== $u1) {
                $rawUnits[] = ['name' => $u3, 'modal' => $modal3, 'harga' => $harga3];
            }
            $rawUnits = array_values(array_filter($rawUnits, fn ($u) => $u['name'] !== ''));
            if (empty($rawUnits)) {
                $skipped++;

                continue;
            }

            // Satuan terkecil = harga terkecil (biasanya yang terakhir).
            $prices = array_map(fn ($u) => $u['harga'], $rawUnits);
            $minPrice = min(array_filter($prices)) ?: (min($prices) ?: 1);

            // qty_to_base tiap satuan = round(harga / harga_terkecil).
            $unitDefs = [];
            foreach ($rawUnits as $u) {
                $qtb = $u['harga'] > 0 && $minPrice > 0 ? round($u['harga'] / $minPrice) : 1;
                $qtb = max(1, (int) $qtb);
                $ratio = $u['harga'] > 0 && $minPrice > 0 ? $u['harga'] / $minPrice : 1;
                if (abs($ratio - $qtb) > 0.05 && $ratio > 1.05) {
                    $warnings[] = "{$kode} {$nama}: {$u['name']} rasio ".round($ratio, 2)." → dibulatkan {$qtb}";
                }
                $unitDefs[] = [
                    'name' => $u['name'],
                    'qty_to_base' => $qtb,
                    'cost' => $u['modal'],
                    'sell' => $u['harga'],
                ];
                $units[$u['name']] = true;
            }

            // Pastikan ada tepat satu base (qty_to_base=1) = satuan termurah.
            $hasBase = collect($unitDefs)->contains(fn ($u) => $u['qty_to_base'] === 1);
            if (! $hasBase) {
                // set yang qty_to_base terkecil jadi 1 (skala ulang).
                $minQtb = min(array_map(fn ($u) => $u['qty_to_base'], $unitDefs));
                foreach ($unitDefs as &$u) {
                    $u['qty_to_base'] = max(1, (int) round($u['qty_to_base'] / $minQtb));
                }
                unset($u);
            }

            if ($pabrik !== '') {
                $suppliers[$pabrik] = true;
            }
            if ($kategori !== '') {
                $categories[$kategori] = true;
            }

            $products[] = [
                'sku' => $kode,
                'name' => $nama,
                'supplier' => $pabrik !== '' ? $pabrik : null,
                'category' => $kategori !== '' ? $kategori : null,
                // Semua produk diaktifkan; petugas menonaktifkan manual belakangan.
                // (Kolom "Aktif"/"PASIF X BULAN" Excel diabaikan.)
                'is_active' => true,
                'units' => $unitDefs,
            ];
        }

        return [
            'products' => $products,
            'suppliers' => array_keys($suppliers),
            'categories' => array_keys($categories),
            'units' => array_keys($units),
            'warnings' => $warnings,
            'valid_count' => count($products),
            'skipped_count' => $skipped,
        ];
    }

    private function num(mixed $v): float
    {
        if (is_numeric($v)) {
            return (float) $v;
        }
        $clean = preg_replace('/[^0-9.]/', '', (string) $v);

        return $clean === '' ? 0.0 : (float) $clean;
    }

    /**
     * @param  array<int, string>  $names
     * @return array<string, int> name → unit id
     */
    private function ensureUnits(array $names, int $userId): array
    {
        $map = [];
        foreach ($names as $name) {
            $unit = Unit::firstOrCreate(
                ['name' => $name],
                ['is_active' => true, 'created_by' => $userId],
            );
            $map[$name] = $unit->id;
        }

        return $map;
    }

    /**
     * @param  array<int, string>  $names
     * @return array<string, int>
     */
    private function ensureCategories(array $names, int $userId): array
    {
        $map = [];
        foreach ($names as $name) {
            $cat = ProductCategory::firstOrCreate(
                ['name' => $name],
                ['code' => Str::slug($name, '_'), 'is_active' => true, 'created_by' => $userId],
            );
            $map[$name] = $cat->id;
        }

        return $map;
    }

    /**
     * @param  array<int, string>  $names
     * @return array<string, int>
     */
    private function ensureSuppliers(array $names, int $userId): array
    {
        $map = [];
        $i = 1;
        foreach ($names as $name) {
            $supplier = Supplier::firstOrCreate(
                ['name' => $name],
                [
                    'code' => 'SUP-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                    'is_active' => true,
                    'created_by' => $userId,
                ],
            );
            $map[$name] = $supplier->id;
            $i++;
        }

        return $map;
    }

    /**
     * @param  array<string, mixed>  $p
     * @param  array<string, int>  $unitIdByName
     * @param  array<string, int>  $catIdByName
     * @param  array<string, int>  $supplierIdByName
     */
    private function importProduct(array $p, array $unitIdByName, array $catIdByName, array $supplierIdByName, int $userId): void
    {
        // Supplier wajib di produk (1 produk = 1 supplier). Kalau kosong → supplier "UMUM".
        $supplierId = $p['supplier'] ? ($supplierIdByName[$p['supplier']] ?? null) : null;
        if ($supplierId === null) {
            $supplierId = Supplier::firstOrCreate(
                ['name' => 'UMUM'],
                ['code' => 'SUP-UMUM', 'is_active' => true, 'created_by' => $userId],
            )->id;
        }

        // Susun units untuk ProductService (butuh unit_id master + qty_to_base).
        $units = [];
        foreach ($p['units'] as $i => $u) {
            $units[] = [
                'unit_id' => $unitIdByName[$u['name']],
                'qty_to_base' => $u['qty_to_base'],
                'barcode' => null,
            ];
        }

        // Paket "Harga Reguler" — item per satuan (unit_index → cost/sell).
        $packageItems = [];
        foreach ($p['units'] as $i => $u) {
            $packageItems[] = [
                'unit_index' => $i,
                'cost_price' => $u['cost'],
                'sell_price' => $u['sell'],
            ];
        }
        $packages = [['name' => 'Harga Reguler', 'items' => $packageItems]];

        $this->products->create(
            [
                'supplier_id' => $supplierId,
                'sku' => $p['sku'],
                'name' => $p['name'],
                'category_id' => $p['category'] ? ($catIdByName[$p['category']] ?? null) : null,
                'is_active' => $p['is_active'],
                'created_by' => $userId,
            ],
            $units,
            $packages,
        );
    }

    /**
     * Reset FK-aman: hapus transaksi turunan dulu, lalu produk & supplier.
     * Customer & user DIPERTAHANKAN.
     */
    private function freshReset(): void
    {
        $order = [
            'invoice_items', 'payment_supplier_allocations', 'payments', 'payment_requests',
            'credit_note_applications', 'credit_note_items', 'credit_notes',
            'invoice_extension_logs', 'invoice_extensions', 'invoices',
            'customer_return_items', 'customer_returns',
            'supplier_return_items', 'supplier_returns',
            'do_items', 'delivery_orders',
            'so_reservations', 'so_items', 'sales_orders',
            'stock_ledgers', 'stock_ledger', 'stock_balances', 'product_batches',
            'stock_opening_items', 'stock_openings',
            'stock_adjustment_items', 'stock_adjustments',
            'stock_opname_items', 'stock_opnames',
            'grn_items', 'goods_receipts', 'grn_attachments',
            'po_items', 'purchase_orders',
            'customer_product_price_packages', 'customer_supplier_credit_limits',
            'product_group_items', 'sales_product_groups', 'product_groups',
            'product_price_package_items', 'product_price_packages',
            'product_units',
            'products', 'suppliers',
        ];

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach ($order as $t) {
            if (DB::getSchemaBuilder()->hasTable($t)) {
                DB::table($t)->delete();
            }
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
}
