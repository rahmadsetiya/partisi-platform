<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kegiatan_petugas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kegiatan_id')->constrained('kegiatan')->cascadeOnDelete();
            $table->foreignId('petugas_id')->constrained('petugas')->restrictOnDelete();
            $table->enum('peran', ['ppl', 'pml']);
            $table->string('label', 30);  // "PPL 1", "PML 2" — auto-generated
            $table->integer('group_id');  // index 0-based internal (cocok dengan partisi_detail)
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['kegiatan_id', 'petugas_id', 'peran']);
            $table->index(['kegiatan_id', 'peran']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kegiatan_petugas');
    }
};
