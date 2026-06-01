<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partisi_detail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sesi_partisi_id')->constrained('sesi_partisi')->cascadeOnDelete();
            $table->foreignId('subsls_id')->constrained('subsls')->restrictOnDelete();
            // FK ke kegiatan_petugas (PPL yang ditugaskan)
            $table->unsignedBigInteger('ppl_id');
            $table->foreign('ppl_id')->references('id')->on('kegiatan_petugas')->restrictOnDelete();
            // FK ke kegiatan_petugas (PML supervisor) — nullable
            $table->unsignedBigInteger('pml_id')->nullable();
            $table->foreign('pml_id')->references('id')->on('kegiatan_petugas')->restrictOnDelete();

            $table->timestamp('created_at')->useCurrent();

            $table->unique(['sesi_partisi_id', 'subsls_id']);
            $table->index(['sesi_partisi_id', 'ppl_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partisi_detail');
    }
};
