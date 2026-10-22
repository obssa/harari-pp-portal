<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ADR-0067: an application may be confirmed by a code sent to the email
     * address instead of the phone.
     */
    public function up(): void
    {
        Schema::table('membership_applications', function (Blueprint $table) {
            $table->timestamp('email_verified_at')->nullable()->after('phone_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('membership_applications', fn (Blueprint $table) => $table->dropColumn('email_verified_at'));
    }
};
