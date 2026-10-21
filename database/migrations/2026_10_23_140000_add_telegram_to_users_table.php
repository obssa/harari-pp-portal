<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staff who link their Telegram account to receive notices there (ADR-0055):
 * the chat (encrypted) and the one-time code that links it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->text('telegram_chat_id')->nullable();
            $table->timestamp('telegram_linked_at')->nullable();
            $table->char('telegram_link_code', 10)->nullable()->unique();
            $table->timestamp('telegram_link_expires_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['telegram_link_code']);
            $table->dropColumn(['telegram_chat_id', 'telegram_linked_at', 'telegram_link_code', 'telegram_link_expires_at']);
        });
    }
};
