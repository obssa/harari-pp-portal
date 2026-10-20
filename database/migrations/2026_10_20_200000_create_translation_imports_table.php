<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Imported translation files (PLAN.md §10 `translation_imports`, ADR-0033):
 * what changed, what was refused, and the earlier values for rollback.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translation_imports', function (Blueprint $table): void {
            $table->id();
            $table->string('locale', 8);
            $table->string('file_name', 190);
            $table->string('format', 8);
            $table->string('status', 16)->default('pending');
            $table->unsignedInteger('added')->default(0);
            $table->unsignedInteger('changed')->default(0);
            $table->unsignedInteger('removed')->default(0);
            $table->json('changes')->nullable();
            $table->json('problems')->nullable();
            $table->json('snapshot')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('rolled_back_at')->nullable();
            $table->timestamps();

            $table->index(['locale', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translation_imports');
    }
};
