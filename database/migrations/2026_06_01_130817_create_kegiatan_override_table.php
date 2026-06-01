<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kegiatan_override', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kegiatan_id')->constrained('kegiatan')->cascadeOnDelete();
            $table->string('idsubsls_a', 30);
            $table->string('idsubsls_b', 30);
            $table->enum('tipe', ['force_connect', 'force_disconnect']);
            $table->text('catatan')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index('kegiatan_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kegiatan_override');
    }
};
