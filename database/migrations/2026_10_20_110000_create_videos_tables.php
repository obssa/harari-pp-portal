<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Videos and live broadcasts (PLAN.md §4 News & Media, §10 `videos`):
 * YouTube, Facebook or Telegram videos with a title per language.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('videos', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            $table->string('provider', 16);
            $table->string('url', 500);
            $table->string('youtube_id', 11)->nullable();
            $table->foreignId('thumbnail_id')->nullable()->constrained('media')->nullOnDelete();
            $table->foreignUlid('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('office_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('league_id')->nullable()->constrained()->nullOnDelete();
            $table->date('recorded_on')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_live')->default(false);
            $table->timestamp('live_starts_at')->nullable();
            $table->timestamp('live_ends_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['site_id', 'recorded_on']);
            $table->index(['site_id', 'is_live', 'live_starts_at']);
        });

        Schema::create('video_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('video_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 8);
            $table->string('title', 200);
            $table->string('slug', 160);
            $table->text('summary')->nullable();
            $table->json('body')->nullable();
            $table->string('status', 16)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['video_id', 'locale']);
            $table->unique(['locale', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_translations');
        Schema::dropIfExists('videos');
    }
};
