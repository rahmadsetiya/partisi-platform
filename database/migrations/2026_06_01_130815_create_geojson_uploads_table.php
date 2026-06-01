<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geojson_uploads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kegiatan_id')->constrained('kegiatan')->cascadeOnDelete();
            $table->enum('level', ['desa', 'subsls']);
            $table->string('nama_file', 255);
            $table->string('path', 500);
            $table->string('muatan_col', 100)->nullable(); // kolom muatan yang dipilih user
            $table->integer('epsg')->default(32750);
            $table->integer('jumlah_fitur')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('uploaded_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geojson_uploads');
    }
};
