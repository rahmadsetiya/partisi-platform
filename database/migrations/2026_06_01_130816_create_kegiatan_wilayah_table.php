<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kegiatan_wilayah', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kegiatan_id')->constrained('kegiatan')->cascadeOnDelete();
            $table->foreignId('subsls_id')->constrained('subsls')->restrictOnDelete();
            $table->integer('muatan');          // nilai muatan untuk kegiatan ini
            $table->string('muatan_col', 100);  // nama kolom asal (audit trail)
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['kegiatan_id', 'subsls_id']);
            $table->index('kegiatan_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kegiatan_wilayah');
    }
};
