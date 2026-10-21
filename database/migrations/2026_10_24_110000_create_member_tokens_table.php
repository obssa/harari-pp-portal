<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sign-ins to the member app (ADR-0057): one row per device, holding a
     * keyed hash of the token the app sends. Signing out deletes the row.
     */
    public function up(): void
    {
        Schema::create('member_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('member_id')->constrained('members')->cascadeOnDelete();
            $table->foreignId('api_key_id')->nullable()->constrained('api_keys')->nullOnDelete();
            $table->text('device');
            $table->char('token_hash', 64)->unique();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_tokens');
    }
};
