<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where each library file is used (PLAN.md §7 "usage tracking"), written by
 * content models through SyncMediaUsage. A used file cannot be deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_usages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->morphs('usable');
            $table->string('context', 64);
            $table->timestamps();

            $table->unique(['media_id', 'usable_type', 'usable_id', 'context'], 'media_usages_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_usages');
    }
};
