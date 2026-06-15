<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kegiatan', function (Blueprint $table) {
            $table->string('satker', 100)->nullable()->after('status');
            $table->index('satker');
        });

        // Backfill dari satker pembuat.
        DB::statement('UPDATE kegiatan SET satker = (SELECT satker FROM users WHERE users.id = kegiatan.created_by)');
    }

    public function down(): void
    {
        Schema::table('kegiatan', function (Blueprint $table) {
            $table->dropIndex(['satker']);
            $table->dropColumn('satker');
        });
    }
};
