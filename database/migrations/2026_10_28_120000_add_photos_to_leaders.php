<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A photo gallery on each leader's profile (ADR-0071): library media ids in
 * the order an editor chose.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leaders', function (Blueprint $table) {
            $table->json('photo_ids')->nullable()->after('portrait_id');
        });
    }

    public function down(): void
    {
        Schema::table('leaders', function (Blueprint $table) {
            $table->dropColumn('photo_ids');
        });
    }
};
