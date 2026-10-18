<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_scopes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->string('scope_type', 16);
            // ULID of an office/league (Phase 2) or a locale code such as "har".
            $table->string('scope_value', 64);
            $table->timestamps();

            $table->unique(['user_id', 'role_id', 'scope_type', 'scope_value'], 'role_scopes_unique');
            $table->index(['scope_type', 'scope_value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_scopes');
    }
};
