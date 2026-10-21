<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Fayda identity a member signs in with (ADR-0058), as a keyed hash
     * of the identifier eSignet gives the portal; never the Fayda number.
     */
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->char('fayda_sub_hash', 64)->nullable()->unique()->after('email_hash');
            $table->timestamp('fayda_linked_at')->nullable()->after('fayda_sub_hash');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropUnique(['fayda_sub_hash']);
            $table->dropColumn(['fayda_sub_hash', 'fayda_linked_at']);
        });
    }
};
