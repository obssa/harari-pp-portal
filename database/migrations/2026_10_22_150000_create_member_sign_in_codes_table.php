<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One-time codes for signing in to the member portal (ADR-0049): only their
 * keyed hash is kept, for ten minutes and five tries.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_sign_in_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('member_id')->constrained()->cascadeOnDelete();
            $table->char('code_hash', 64);
            $table->timestamp('expires_at');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('created_at')->nullable();
            $table->index(['member_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_sign_in_codes');
    }
};
