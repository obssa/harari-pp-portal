<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * spatie/laravel-settings storage, scoped per site (PLAN.md §7); see
 * App\Domain\Sites\Settings\SiteSettingsRepository.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('settings.repositories.database.table') ?? 'settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained()->restrictOnDelete();

            $table->string('group');
            $table->string('name');
            $table->boolean('locked')->default(false);
            $table->json('payload');

            $table->timestamps();

            $table->unique(['site_id', 'group', 'name']);
        });
    }
};
