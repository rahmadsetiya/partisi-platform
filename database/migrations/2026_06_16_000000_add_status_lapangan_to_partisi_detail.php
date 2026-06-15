<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partisi_detail', function (Blueprint $table) {
            // Progres pencacahan lapangan: belum | proses | selesai
            $table->string('status_lapangan', 10)->default('belum')->after('pml_id');
        });
    }

    public function down(): void
    {
        Schema::table('partisi_detail', function (Blueprint $table) {
            $table->dropColumn('status_lapangan');
        });
    }
};
