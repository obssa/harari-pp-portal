<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Redirects (PLAN.md §7 Redirects & SEO): old addresses that moved, made
 * automatically when a published slug changes or entered by hand.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('redirects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            $table->string('from_path', 300);
            $table->string('to_path', 500);
            $table->unsignedSmallInteger('status')->default(301);
            $table->unsignedInteger('hits')->default(0);
            $table->timestamp('last_hit_at')->nullable();
            $table->boolean('automatic')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['site_id', 'from_path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redirects');
    }
};
