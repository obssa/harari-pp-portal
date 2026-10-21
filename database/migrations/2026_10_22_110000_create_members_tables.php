<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Members (PLAN.md §5, §10, ADR-0045) and the notes staff keep on
 * applications and members. Personal details are encrypted as on the
 * applications, with keyed hashes for lookups; notes are encrypted too.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            $table->string('member_no', 20)->unique();
            $table->foreignUlid('application_id')->nullable()->constrained('membership_applications')->nullOnDelete();
            $table->string('status', 16)->default('active');
            $table->string('source', 16)->default('application');

            $table->text('name');
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

            $table->date('joined_on');
            $table->date('renewed_on')->nullable();
            $table->date('renewal_due_on')->nullable();
            $table->timestamp('erased_at')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'status']);
            $table->index(['woreda_id', 'status']);
            $table->index('renewal_due_on');
        });

        Schema::create('people_notes', function (Blueprint $table): void {
            $table->id();
            $table->string('notable_type');
            $table->string('notable_id', 36);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 16);
            $table->text('body')->nullable();
            $table->timestamps();

            $table->index(['notable_type', 'notable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('people_notes');
        Schema::dropIfExists('members');
    }
};
