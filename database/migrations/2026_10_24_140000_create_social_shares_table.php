<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Posts shared from the admin to the party's Facebook pages and Telegram
     * channels (ADR-0059), one row per account, with the outcome.
     */
    public function up(): void
    {
        Schema::create('social_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('post_id')->constrained('posts')->cascadeOnDelete();
            $table->foreignUlid('social_source_id')->nullable()->constrained('social_sources')->nullOnDelete();
            $table->string('platform', 20);
            $table->string('account', 190);
            $table->string('locale', 5);
            $table->text('message');
            $table->string('status', 10)->default('queued')->index();
            $table->string('external_id', 190)->nullable();
            $table->string('external_url', 500)->nullable();
            $table->string('error', 190)->nullable();
            $table->foreignId('shared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('shared_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_shares');
    }
};
