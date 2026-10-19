<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Content records use ULIDs (PLAN.md §10), while users and media keep
 * numeric ids. Polymorphic columns that can point at content therefore hold
 * strings: the audit log subject and the media usage owner.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $table): void {
            $table->string('subject_id', 36)->nullable()->change();
        });

        Schema::table('media_usages', function (Blueprint $table): void {
            $table->string('usable_id', 36)->change();
        });
    }

    public function down(): void
    {
        Schema::table('media_usages', function (Blueprint $table): void {
            $table->unsignedBigInteger('usable_id')->change();
        });

        Schema::table('activity_log', function (Blueprint $table): void {
            $table->unsignedBigInteger('subject_id')->nullable()->change();
        });
    }
};
