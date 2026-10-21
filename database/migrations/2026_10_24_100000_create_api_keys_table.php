<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Keys for the public API (ADR-0056). Only a keyed hash of each key is
     * kept; the prefix lets staff tell keys apart.
     */
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('prefix', 16);
            $table->char('key_hash', 64)->unique();
            $table->json('scopes');
            $table->unsignedInteger('rate_per_minute')->default(60);
            $table->string('notes', 500)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_keys');
    }
};
