<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the last renewal reminder went out (ADR-0046), so each renewal
 * period gets one reminder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table): void {
            $table->date('renewal_reminded_on')->nullable()->after('renewal_due_on');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table): void {
            $table->dropColumn('renewal_reminded_on');
        });
    }
};
