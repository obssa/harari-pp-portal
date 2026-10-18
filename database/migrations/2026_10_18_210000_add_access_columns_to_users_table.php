<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('status', 16)->default('active')->after('password')->index();
            $table->string('locale', 8)->nullable()->after('status');
            $table->timestamp('last_login_at')->nullable()->after('remember_token');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
            // Filament authenticator-app MFA; both stored encrypted by the model casts.
            $table->text('app_authentication_secret')->nullable()->after('last_login_ip');
            $table->text('app_authentication_recovery_codes')->nullable()->after('app_authentication_secret');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn([
                'status', 'locale', 'last_login_at', 'last_login_ip',
                'app_authentication_secret', 'app_authentication_recovery_codes',
            ]);
        });
    }
};
