<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Membership applications (PLAN.md §4 Get Involved, §10, ADR-0044). Names,
 * birth dates, ID numbers and contact details are encrypted; the ID number,
 * phone and email are also kept as keyed hashes so duplicates and a
 * person's records can be found without decrypting. Sex and birth year stay
 * readable for aggregated reports only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_applications', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            $table->string('reference', 16)->unique();
            $table->string('locale', 8);
            $table->string('status', 16)->default('started');

            $table->text('name')->nullable();
            $table->string('sex', 8)->nullable();
            $table->text('birth_date')->nullable();
            $table->unsignedSmallInteger('birth_year')->nullable();
            $table->string('id_type', 16)->nullable();
            $table->text('id_number')->nullable();
            $table->char('id_hash', 64)->nullable()->index();
            $table->string('education', 32)->nullable();
            $table->string('occupation', 120)->nullable();

            $table->foreignUlid('woreda_id')->nullable()->constrained('offices')->nullOnDelete();
            $table->foreignUlid('kebele_id')->nullable()->constrained('offices')->nullOnDelete();
            $table->foreignUlid('basic_org_id')->nullable()->constrained('offices')->nullOnDelete();
            $table->text('address_note')->nullable();

            $table->text('phone')->nullable();
            $table->char('phone_hash', 64)->nullable()->index();
            $table->text('email')->nullable();
            $table->char('email_hash', 64)->nullable()->index();

            $table->char('code_hash', 64)->nullable();
            $table->timestamp('code_expires_at')->nullable();
            $table->unsignedTinyInteger('code_attempts')->default(0);
            $table->unsignedTinyInteger('codes_sent')->default(0);
            $table->timestamp('code_sent_at')->nullable();

            $table->timestamp('consented_at')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('erased_at')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'status', 'submitted_at']);
            $table->index(['woreda_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_applications');
    }
};
