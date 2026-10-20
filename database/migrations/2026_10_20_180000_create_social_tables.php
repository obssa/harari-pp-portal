<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The social media hub (PLAN.md §4, §10 `social_sources`, `social_posts`,
 * ADR-0031): accounts of the branch, its offices and leagues, and the
 * public posts read from them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_sources', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            $table->string('platform', 16);
            $table->string('handle', 160);
            $table->string('name', 160);
            $table->foreignUlid('office_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('league_id')->nullable()->constrained()->nullOnDelete();
            $table->text('credentials')->nullable();
            $table->boolean('enabled')->default(true);
            $table->unsignedSmallInteger('interval_minutes')->default(60);
            $table->timestamp('last_fetched_at')->nullable();
            $table->text('last_error')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['site_id', 'platform', 'handle']);
        });

        Schema::create('social_posts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('social_source_id')->constrained()->cascadeOnDelete();
            $table->string('external_id', 190);
            $table->timestamp('posted_at');
            $table->text('text')->nullable();
            $table->string('permalink', 500);
            $table->foreignId('image_id')->nullable()->constrained('media')->nullOnDelete();
            $table->boolean('has_video')->default(false);
            $table->json('metrics')->nullable();
            $table->boolean('is_hidden')->default(false);
            $table->boolean('is_pinned')->default(false);
            $table->timestamps();

            $table->unique(['social_source_id', 'external_id']);
            $table->index(['is_hidden', 'posted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_posts');
        Schema::dropIfExists('social_sources');
    }
};
