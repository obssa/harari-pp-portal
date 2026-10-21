<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Email and SMS campaigns (PLAN.md §5, §10; ADR-0052): the text per language
 * and the audience of each campaign, and one delivery per recipient with a
 * private token for the unsubscribe and click links. Deliveries never hold
 * an address in clear, only its keyed hash to send each address once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            $table->string('name', 120);
            $table->string('channel', 8);
            $table->json('audience');
            $table->json('content');
            $table->string('status', 16)->default('draft');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['site_id', 'status']);
        });

        Schema::create('campaign_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('recipient_type', 16);
            $table->string('recipient_id', 36);
            $table->string('locale', 5);
            $table->char('address_hash', 64);
            $table->char('token', 12)->unique();
            $table->string('status', 12)->default('queued');
            $table->string('error', 190)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->unsignedSmallInteger('clicks')->default(0);
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();
            $table->unique(['campaign_id', 'address_hash']);
            $table->index(['campaign_id', 'status']);
        });

        foreach (['members', 'volunteers'] as $people) {
            Schema::table($people, function (Blueprint $table): void {
                $table->timestamp('campaigns_opt_out_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['members', 'volunteers'] as $people) {
            Schema::table($people, function (Blueprint $table): void {
                $table->dropColumn('campaigns_opt_out_at');
            });
        }
        Schema::dropIfExists('campaign_deliveries');
        Schema::dropIfExists('campaigns');
    }
};
