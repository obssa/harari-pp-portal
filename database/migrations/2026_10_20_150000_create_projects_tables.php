<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Projects and statistics (PLAN.md §4 Programmes & Achievements, §10
 * `projects`, `statistics`, ADR-0027). Sectors are categories of the type
 * "sector"; photos come from a gallery album.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('sector_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignUlid('office_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 16)->default('planned');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->decimal('budget', 16, 2)->nullable();
            $table->boolean('budget_public')->default(false);
            $table->unsignedTinyInteger('progress')->nullable();
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
            $table->foreignId('cover_id')->nullable()->constrained('media')->nullOnDelete();
            $table->foreignUlid('gallery_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('featured')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['site_id', 'status']);
            $table->index(['site_id', 'sector_id']);
        });

        Schema::create('project_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 8);
            $table->string('title', 200);
            $table->string('slug', 160);
            $table->text('summary')->nullable();
            $table->json('body')->nullable();
            $table->string('location', 200)->nullable();
            $table->string('status', 16)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->mediumText('search_text')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'locale']);
            $table->unique(['locale', 'slug']);
        });

        Schema::create('statistics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('sector_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignUlid('project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->decimal('value', 20, 2);
            $table->date('as_of')->nullable();
            $table->string('source_url', 500)->nullable();
            $table->boolean('on_achievements')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['site_id', 'sector_id', 'sort']);
        });

        Schema::create('statistic_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('statistic_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 8);
            $table->string('label', 200);
            $table->string('slug', 160);
            $table->string('unit', 40)->nullable();
            $table->string('source', 300)->nullable();
            $table->timestamps();

            $table->unique(['statistic_id', 'locale']);
            $table->unique(['locale', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statistic_translations');
        Schema::dropIfExists('statistics');
        Schema::dropIfExists('project_translations');
        Schema::dropIfExists('projects');
    }
};
