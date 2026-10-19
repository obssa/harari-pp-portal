<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Photo galleries (PLAN.md §4 News & Media, §10 `galleries`, `gallery_items`):
 * albums of library photos with a caption per language.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('galleries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            $table->foreignId('cover_id')->nullable()->constrained('media')->nullOnDelete();
            $table->foreignUlid('office_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('league_id')->nullable()->constrained()->nullOnDelete();
            $table->date('taken_on')->nullable();
            $table->boolean('allow_download')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['site_id', 'taken_on']);
        });

        Schema::create('gallery_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('gallery_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 8);
            $table->string('title', 200);
            $table->string('slug', 160);
            $table->text('summary')->nullable();
            $table->json('body')->nullable();
            $table->string('status', 16)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['gallery_id', 'locale']);
            $table->unique(['locale', 'slug']);
        });

        Schema::create('gallery_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('gallery_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->json('caption')->nullable();
            $table->timestamps();

            $table->index(['gallery_id', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gallery_items');
        Schema::dropIfExists('gallery_translations');
        Schema::dropIfExists('galleries');
    }
};
