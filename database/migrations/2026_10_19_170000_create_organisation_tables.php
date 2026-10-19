<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Offices, leaders and leagues (PLAN.md §4, §7, §10), each written per
 * language, and their links to posts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offices', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            $table->string('type', 24);
            $table->foreignUlid('parent_id')->nullable()->constrained('offices')->nullOnDelete();
            $table->ulid('head_leader_id')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email', 190)->nullable();
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
            $table->foreignId('cover_image_id')->nullable()->constrained('media')->nullOnDelete();
            $table->unsignedInteger('sort')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['site_id', 'type', 'sort']);
            $table->index(['site_id', 'parent_id']);
        });

        Schema::create('leagues', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            $table->ulid('head_leader_id')->nullable();
            $table->foreignId('logo_id')->nullable()->constrained('media')->nullOnDelete();
            $table->foreignId('cover_image_id')->nullable()->constrained('media')->nullOnDelete();
            $table->string('phone', 40)->nullable();
            $table->string('email', 190)->nullable();
            $table->json('socials')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['site_id', 'sort']);
        });

        Schema::create('leaders', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            $table->string('body', 32);
            $table->foreignUlid('office_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('league_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('rank')->default(100);
            $table->date('term_start')->nullable();
            $table->date('term_end')->nullable();
            $table->foreignId('portrait_id')->nullable()->constrained('media')->nullOnDelete();
            $table->string('phone', 40)->nullable();
            $table->string('email', 190)->nullable();
            $table->json('socials')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['site_id', 'body', 'rank']);
        });

        Schema::table('offices', function (Blueprint $table): void {
            $table->foreign('head_leader_id')->references('id')->on('leaders')->nullOnDelete();
        });
        Schema::table('leagues', function (Blueprint $table): void {
            $table->foreign('head_leader_id')->references('id')->on('leaders')->nullOnDelete();
        });

        Schema::create('office_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('office_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 8);
            $table->string('name', 200);
            $table->string('slug', 160);
            $table->text('summary')->nullable();
            $table->json('body')->nullable();
            $table->text('address')->nullable();
            $table->string('hours', 200)->nullable();
            $table->string('status', 16)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['office_id', 'locale']);
            $table->unique(['locale', 'slug']);
        });

        Schema::create('league_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('league_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 8);
            $table->string('name', 200);
            $table->string('slug', 160);
            $table->text('summary')->nullable();
            $table->json('body')->nullable();
            $table->string('status', 16)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['league_id', 'locale']);
            $table->unique(['locale', 'slug']);
        });

        Schema::create('leader_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('leader_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 8);
            $table->string('name', 200);
            $table->string('slug', 160);
            $table->string('position', 200)->nullable();
            $table->text('summary')->nullable();
            $table->json('body')->nullable();
            $table->string('status', 16)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['leader_id', 'locale']);
            $table->unique(['locale', 'slug']);
        });

        Schema::create('office_post', function (Blueprint $table): void {
            $table->foreignUlid('office_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('post_id')->constrained()->cascadeOnDelete();
            $table->primary(['office_id', 'post_id']);
            $table->index('post_id');
        });

        Schema::create('league_post', function (Blueprint $table): void {
            $table->foreignUlid('league_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('post_id')->constrained()->cascadeOnDelete();
            $table->primary(['league_id', 'post_id']);
            $table->index('post_id');
        });

        Schema::create('leader_post', function (Blueprint $table): void {
            $table->foreignUlid('leader_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('post_id')->constrained()->cascadeOnDelete();
            $table->primary(['leader_id', 'post_id']);
            $table->index('post_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leader_post');
        Schema::dropIfExists('league_post');
        Schema::dropIfExists('office_post');
        Schema::dropIfExists('leader_translations');
        Schema::dropIfExists('league_translations');
        Schema::dropIfExists('office_translations');
        Schema::table('leagues', fn (Blueprint $table) => $table->dropForeign(['head_leader_id']));
        Schema::table('offices', fn (Blueprint $table) => $table->dropForeign(['head_leader_id']));
        Schema::dropIfExists('leaders');
        Schema::dropIfExists('leagues');
        Schema::dropIfExists('offices');
    }
};
