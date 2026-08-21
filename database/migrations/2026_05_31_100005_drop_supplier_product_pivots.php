<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Hapus struktur harga & tagging multi-supplier lama. Digantikan oleh:
 * - products.supplier_id (1 produk = 1 supplier)
 * - product_price_packages + product_price_package_items (paket harga)
 *
 * Data harga lama tidak dimigrasikan (hanya data uji coba, disepakati
 * dihapus). Item transaksi historis (so_items dst) tidak terpengaruh
 * karena menyimpan snapshot harga & supplier sendiri.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('supplier_product_units');
        Schema::dropIfExists('supplier_products');
    }

    public function down(): void
    {
        // Tidak di-restore — struktur lama sudah digantikan sepenuhnya.
    }
};
