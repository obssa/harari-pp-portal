<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The homepage carousel (ADR-0070): slides an editor orders, schedules and
 * writes in each language.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hero_slides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            $table->string('name', 120);
            $table->string('layout', 20)->default('photo');
            $table->foreignId('image_id')->nullable()->constrained('media')->nullOnDelete();
            $table->unsignedTinyInteger('overlay')->default(80);
            $table->string('align', 10)->default('start');
            $table->json('content')->nullable();
            $table->json('buttons')->nullable();
            $table->json('locales')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedSmallInteger('duration')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['site_id', 'active', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hero_slides');
    }
};
