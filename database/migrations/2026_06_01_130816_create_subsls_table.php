<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subsls', function (Blueprint $table) {
            $table->id();
            $table->string('idsubsls', 30)->unique(); // "7316010018000200" — kode BPS unik
            $table->string('kdsubsls', 10);           // "00"

            // Hierarki administratif
            $table->string('kdprov', 10);
            $table->string('nmprov', 100);
            $table->string('kdkab', 10);
            $table->string('nmkab', 100);
            $table->string('kdkec', 10);
            $table->string('nmkec', 100);
            $table->string('kddesa', 10);
            $table->string('nmdesa', 100);
            $table->string('kdsls', 10);
            $table->string('nmsls', 100);
            $table->string('idsls', 20)->nullable();

            // Geometri (disimpan sebagai GeoJSON — tanpa PostGIS)
            $table->json('geometry');
            $table->double('centroid_lat');
            $table->double('centroid_lon');
            $table->double('luas')->nullable();

            $table->timestamps();

            $table->index(['kdprov', 'kdkab', 'kdkec']);
            $table->index('nmdesa');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subsls');
    }
};
