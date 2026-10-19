<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pages (PLAN.md §7: templates, nesting, block editor) and menus (header,
 * footer columns, utility bar), both per language.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            $table->string('template', 32)->default('default');
            $table->foreignUlid('parent_id')->nullable()->constrained('pages')->nullOnDelete();
            $table->unsignedInteger('sort')->default(0);
            $table->foreignId('featured_image_id')->nullable()->constrained('media')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['site_id', 'parent_id', 'sort']);
        });

        Schema::create('page_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('page_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 8);
            $table->string('title', 200);
            $table->string('slug', 160);
            $table->text('excerpt')->nullable();
            $table->json('body')->nullable();
            $table->string('seo_title', 70)->nullable();
            $table->string('seo_description', 170)->nullable();
            $table->boolean('noindex')->default(false);
            $table->string('status', 16)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('word_count')->default(0);
            $table->unsignedSmallInteger('reading_time')->default(1);
            $table->timestamps();

            $table->unique(['page_id', 'locale']);
            $table->unique(['locale', 'slug']);
            $table->index(['locale', 'status']);
        });

        Schema::create('menus', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            $table->string('location', 32);
            $table->json('items');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['site_id', 'location']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menus');
        Schema::dropIfExists('page_translations');
        Schema::dropIfExists('pages');
    }
};
