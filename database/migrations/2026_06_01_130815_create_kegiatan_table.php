<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kegiatan', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 150);
            $table->enum('jenis', ['berkala', 'insidentil'])->default('berkala');
            $table->year('tahun');
            $table->string('gelombang', 50)->nullable(); // "Maret", "Triwulan I", null jika insidentil
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai')->nullable();
            $table->text('deskripsi')->nullable();
            $table->enum('status', ['draft', 'aktif', 'selesai'])->default('draft');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kegiatan');
    }
};
