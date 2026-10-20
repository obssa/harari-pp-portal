<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Forms and what people send with them (PLAN.md §4 Contact and Get
 * involved, §10 `forms`, `form_submissions`, ADR-0028). Everything a
 * person writes is encrypted; the ticket reference is random.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forms', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            $table->string('type', 16)->default('contact');
            $table->foreignUlid('office_id')->nullable()->constrained()->nullOnDelete();
            $table->string('notify_email', 190)->nullable();
            $table->json('fields')->nullable();
            $table->boolean('anonymous_allowed')->default(false);
            $table->unsignedTinyInteger('response_days')->default(3);
            $table->boolean('active')->default(true);
            $table->boolean('on_contact_page')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('form_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('form_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 8);
            $table->string('title', 200);
            $table->string('slug', 160);
            $table->text('intro')->nullable();
            $table->text('success_message')->nullable();
            $table->string('autoreply_subject', 200)->nullable();
            $table->text('autoreply_body')->nullable();
            $table->string('status', 16)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['form_id', 'locale']);
            $table->unique(['locale', 'slug']);
        });

        Schema::create('form_submissions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('form_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('office_id')->nullable()->constrained()->nullOnDelete();
            $table->string('locale', 8);
            $table->string('reference', 16)->unique();
            $table->text('name')->nullable();
            $table->text('email')->nullable();
            $table->text('phone')->nullable();
            $table->longText('answers');
            $table->json('files')->nullable();
            $table->string('status', 16)->default('new');
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'status', 'created_at']);
            $table->index(['office_id', 'status']);
            $table->index(['assignee_id', 'status']);
        });

        Schema::create('submission_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('form_submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 16);
            $table->text('body')->nullable();
            $table->timestamps();

            $table->index(['form_submission_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_notes');
        Schema::dropIfExists('form_submissions');
        Schema::dropIfExists('form_translations');
        Schema::dropIfExists('forms');
    }
};
