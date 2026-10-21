<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When an email was first shown, only if open counting is switched on
 * (ADR-0053; off by default).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_deliveries', function (Blueprint $table): void {
            $table->timestamp('opened_at')->nullable()->after('sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('campaign_deliveries', function (Blueprint $table): void {
            $table->dropColumn('opened_at');
        });
    }
};
