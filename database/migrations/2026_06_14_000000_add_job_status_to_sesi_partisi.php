<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sesi_partisi', function (Blueprint $table) {
            // antri | proses | selesai | gagal (null = sesi manual / lama)
            $table->string('job_status', 20)->nullable()->after('status');
            $table->text('job_error')->nullable()->after('job_status');
        });
    }

    public function down(): void
    {
        Schema::table('sesi_partisi', function (Blueprint $table) {
            $table->dropColumn(['job_status', 'job_error']);
        });
    }
};
