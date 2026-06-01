<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sesi_partisi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kegiatan_id')->constrained('kegiatan')->cascadeOnDelete();
            $table->string('nama', 100)->nullable();          // label opsional ("Coba 1", "Final")
            $table->enum('tipe', ['auto', 'manual']);
            $table->integer('n_ppl');
            $table->integer('n_pml');
            $table->double('cv')->nullable();                 // Coefficient of Variation (kualitas)
            $table->integer('epsg')->default(32750);
            $table->json('config')->nullable();               // snapshot config algoritma
            $table->enum('status', ['draft', 'final'])->default('draft');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();

            $table->index(['kegiatan_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sesi_partisi');
    }
};
