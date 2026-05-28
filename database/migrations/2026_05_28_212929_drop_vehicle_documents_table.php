<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('vehicle_documents');
    }

    public function down(): void
    {
        // Tidak diisi karena tabel vehicle_documents sengaja dihapus permanen
        // (lihat migration `create_vehicle_documents_table` di histori migration
        // kalau perlu restore manual).
    }
};
