<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('driver_documents');
    }

    public function down(): void
    {
        // Tidak diisi karena tabel driver_documents sengaja dihapus permanen
        // (lihat migration `create_driver_documents_table` di histori migration
        // kalau perlu restore manual).
    }
};
