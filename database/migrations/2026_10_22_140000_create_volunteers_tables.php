<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Volunteers and what they do (PLAN.md §5, ADR-0048): contact details
 * encrypted with keyed hashes, skills, availability and languages as lists,
 * and assignments to events or tasks with the hours given.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('volunteers', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            $table->string('status', 16)->default('new');
            $table->string('source', 16)->default('form');
            $table->string('locale', 5)->default('en');
            $table->text('name');
            $table->text('phone')->nullable();
            $table->char('phone_hash', 64)->nullable()->index();
            $table->text('email')->nullable();
            $table->char('email_hash', 64)->nullable()->index();
            $table->foreignUlid('woreda_id')->nullable()->constrained('offices')->nullOnDelete();
            $table->json('skills');
            $table->json('availability');
            $table->json('languages');
            $table->text('message')->nullable();
            $table->foreignUlid('member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->timestamp('consented_at')->nullable();
            $table->timestamp('erased_at')->nullable();
            $table->timestamps();
            $table->index(['site_id', 'status']);
        });

        Schema::create('volunteer_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('volunteer_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('event_id')->nullable()->constrained()->nullOnDelete();
            $table->string('task', 120);
            $table->date('on_date')->nullable();
            $table->string('status', 16)->default('planned');
            $table->decimal('hours', 6, 2)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['volunteer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('volunteer_assignments');
        Schema::dropIfExists('volunteers');
    }
};
