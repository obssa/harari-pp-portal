<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The homepage builder (PLAN.md §7, §10 `homepage_sections`): one row per
 * section and site, created with defaults on first use (ADR-0019).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homepage_sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            $table->string('key', 32);
            $table->boolean('enabled')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->json('settings')->nullable();
            $table->json('curated_ids')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['site_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homepage_sections');
    }
};
