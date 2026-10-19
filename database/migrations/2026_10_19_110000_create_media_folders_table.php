<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Folders of the media library (PLAN.md §7), nested, per site.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_folders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('media_folders')->restrictOnDelete();
            $table->string('name', 120);
            $table->timestamps();

            $table->index(['site_id', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_folders');
    }
};
