<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Event registrations (PLAN.md §10 `event_registrations`, ADR-0025). Names,
 * phone numbers, email addresses and ticket codes are encrypted; keyed
 * hashes find duplicates and tickets without decrypting.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_registrations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('event_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 8);
            $table->text('name');
            $table->text('phone')->nullable();
            $table->text('email')->nullable();
            $table->char('phone_hash', 64)->nullable();
            $table->char('email_hash', 64)->nullable();
            $table->string('status', 20);
            $table->text('token');
            $table->char('token_hash', 64)->unique();
            $table->timestamp('checked_in_at')->nullable();
            $table->foreignId('checked_in_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('promoted_at')->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'status', 'created_at']);
            $table->index(['event_id', 'phone_hash']);
            $table->index(['event_id', 'email_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_registrations');
    }
};
