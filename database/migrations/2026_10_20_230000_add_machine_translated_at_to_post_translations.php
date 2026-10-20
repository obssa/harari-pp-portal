<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A post language filled in by machine translation and not yet checked by a
 * person (ADR-0035).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('post_translations', function (Blueprint $table): void {
            $table->timestamp('machine_translated_at')->nullable()->after('published_at');
        });
    }

    public function down(): void
    {
        Schema::table('post_translations', function (Blueprint $table): void {
            $table->dropColumn('machine_translated_at');
        });
    }
};
