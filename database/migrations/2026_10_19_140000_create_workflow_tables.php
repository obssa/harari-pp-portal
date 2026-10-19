<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Editorial workflow (PLAN.md §7, ADR-0013): internal comments, in-app
 * notifications for staff, and outgoing webhooks with their delivery log.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_comments', function (Blueprint $table): void {
            $table->id();
            $table->string('commentable_type');
            $table->string('commentable_id', 36);
            $table->string('locale', 8)->nullable();
            $table->string('kind', 16)->default('comment');
            $table->text('body');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['commentable_type', 'commentable_id', 'created_at'], 'content_comments_lookup');
        });

        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }

        Schema::create('webhooks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            $table->string('name', 120);
            $table->string('url', 500);
            $table->text('secret');
            $table->json('events');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_delivered_at')->nullable();
            $table->timestamps();
        });

        Schema::create('webhook_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('webhook_id')->constrained()->cascadeOnDelete();
            $table->string('event', 64);
            $table->json('payload');
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->string('error', 500)->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['webhook_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhooks');
        Schema::dropIfExists('content_comments');
    }
};
