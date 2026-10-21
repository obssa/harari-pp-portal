<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Journalists and outlets for press releases (PLAN.md §5, ADR-0054), and a
 * line per press release sent to each of them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_contacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            $table->text('name');
            $table->string('outlet', 120);
            $table->string('position', 120)->nullable();
            $table->text('email');
            $table->char('email_hash', 64)->index();
            $table->text('phone')->nullable();
            $table->char('phone_hash', 64)->nullable()->index();
            $table->json('languages');
            $table->text('notes')->nullable();
            $table->char('token', 24)->unique();
            $table->timestamp('opted_out_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('press_distributions', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_contact_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('status', 12)->default('queued');
            $table->string('error', 190)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['post_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('press_distributions');
        Schema::dropIfExists('media_contacts');
    }
};
