<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Newsletter and SMS subscribers (PLAN.md §10, ADR-0051): contact details
 * encrypted with keyed hashes, each channel confirmed on its own (double
 * opt-in), and a private link token for the preference centre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscribers', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            $table->string('locale', 5);
            $table->text('email')->nullable();
            $table->char('email_hash', 64)->nullable()->index();
            $table->text('phone')->nullable();
            $table->char('phone_hash', 64)->nullable()->index();
            $table->json('channels');
            $table->json('topics');
            $table->text('token');
            $table->char('token_hash', 64)->unique();
            $table->char('code_hash', 64)->nullable();
            $table->timestamp('code_expires_at')->nullable();
            $table->unsignedTinyInteger('code_attempts')->default(0);
            $table->timestamp('code_sent_at')->nullable();
            $table->timestamp('email_confirmed_at')->nullable();
            $table->timestamp('phone_confirmed_at')->nullable();
            $table->string('source', 16)->default('form');
            $table->timestamp('consented_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamp('erased_at')->nullable();
            $table->timestamps();
            $table->index(['site_id', 'unsubscribed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscribers');
    }
};
