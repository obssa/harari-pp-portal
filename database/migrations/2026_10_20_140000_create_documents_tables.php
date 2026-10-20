<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The documents library (PLAN.md §4 Resources, §10 `documents`,
 * `document_translations`, ADR-0026): a file per language with its version,
 * the earlier versions, and related documents. Vacancies and tenders are
 * documents with a deadline.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            $table->string('type', 20);
            $table->unsignedSmallInteger('year')->nullable();
            $table->date('issued_on')->nullable();
            $table->timestamp('deadline_at')->nullable();
            $table->foreignUlid('office_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('league_id')->nullable()->constrained()->nullOnDelete();
            // "members" is kept for the member portal (Phase 6); until then everything is public.
            $table->string('access', 10)->default('public');
            $table->unsignedInteger('download_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['site_id', 'type', 'year']);
            $table->index(['site_id', 'deadline_at']);
        });

        Schema::create('document_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('document_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 8);
            $table->string('title', 250);
            $table->string('slug', 160);
            $table->text('summary')->nullable();
            $table->json('body')->nullable();
            $table->foreignId('file_id')->nullable()->constrained('media')->nullOnDelete();
            $table->unsignedSmallInteger('version')->default(1);
            $table->string('version_note', 300)->nullable();
            $table->string('status', 16)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->mediumText('search_text')->nullable();
            $table->timestamps();

            $table->unique(['document_id', 'locale']);
            $table->unique(['locale', 'slug']);
        });

        Schema::create('document_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('document_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 8);
            $table->foreignId('media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->unsignedSmallInteger('version');
            $table->string('note', 300)->nullable();
            $table->timestamp('replaced_at');
            $table->foreignId('replaced_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['document_id', 'locale', 'version']);
        });

        Schema::create('document_related', function (Blueprint $table): void {
            $table->foreignUlid('document_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('related_id')->constrained('documents')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->primary(['document_id', 'related_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_related');
        Schema::dropIfExists('document_versions');
        Schema::dropIfExists('document_translations');
        Schema::dropIfExists('documents');
    }
};
