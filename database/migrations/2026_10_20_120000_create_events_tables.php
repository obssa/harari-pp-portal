<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Events (PLAN.md §4 Get involved, §10 `events`, `event_translations`):
 * dates, venue or online link, agenda per language and speakers. The
 * registration columns are used from step 3.4.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->boolean('all_day')->default(false);
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
            $table->string('online_url', 500)->nullable();
            $table->foreignId('cover_id')->nullable()->constrained('media')->nullOnDelete();
            $table->foreignUlid('office_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('league_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('registration_enabled')->default(false);
            $table->unsignedInteger('capacity')->nullable();
            $table->boolean('waitlist')->default(true);
            $table->timestamp('registration_closes_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['site_id', 'starts_at']);
        });

        Schema::create('event_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('event_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 8);
            $table->string('title', 200);
            $table->string('slug', 160);
            $table->text('summary')->nullable();
            $table->json('body')->nullable();
            $table->string('venue', 200)->nullable();
            $table->text('address')->nullable();
            $table->json('agenda')->nullable();
            $table->string('status', 16)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->mediumText('search_text')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'locale']);
            $table->unique(['locale', 'slug']);
        });

        Schema::create('event_leader', function (Blueprint $table): void {
            $table->foreignUlid('event_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('leader_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->primary(['event_id', 'leader_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_leader');
        Schema::dropIfExists('event_translations');
        Schema::dropIfExists('events');
    }
};
