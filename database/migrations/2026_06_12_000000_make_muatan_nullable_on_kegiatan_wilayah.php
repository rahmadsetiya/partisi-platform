<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kegiatan_wilayah', function (Blueprint $table) {
            // muatan boleh NULL = belum diisi (geometri bisa diupload tanpa muatan)
            $table->integer('muatan')->nullable()->change();
            // muatan_col jadi label sumber muatan (nama kolom | (seragam) | (import: file) | (manual))
            $table->string('muatan_col', 100)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('kegiatan_wilayah', function (Blueprint $table) {
            $table->integer('muatan')->nullable(false)->change();
            $table->string('muatan_col', 100)->nullable(false)->change();
        });
    }
};
