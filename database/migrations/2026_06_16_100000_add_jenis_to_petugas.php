<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('petugas', function (Blueprint $table) {
            // organik = pegawai BPS (tidak boleh PPL), mitra = bisa PPL/PML
            $table->enum('jenis', ['organik', 'mitra'])->default('mitra')->after('nama');
        });
    }

    public function down(): void
    {
        Schema::table('petugas', function (Blueprint $table) {
            $table->dropColumn('jenis');
        });
    }
};
