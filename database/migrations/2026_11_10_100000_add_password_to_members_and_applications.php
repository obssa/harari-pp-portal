<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ADR-0080: members sign in with their phone number and a password,
     * chosen on the application and carried over when it is approved.
     */
    public function up(): void
    {
        Schema::table('membership_applications', function (Blueprint $table) {
            $table->string('password')->nullable()->after('email_hash');
        });
        Schema::table('members', function (Blueprint $table) {
            $table->string('password')->nullable()->after('email_hash');
        });
    }

    public function down(): void
    {
        Schema::table('membership_applications', fn (Blueprint $table) => $table->dropColumn('password'));
        Schema::table('members', fn (Blueprint $table) => $table->dropColumn('password'));
    }
};
