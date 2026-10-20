<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cookieless page view counts (PLAN.md §7 analytics, ADR-0040): only daily
 * totals per page and language, and per referring site, kind of device and
 * country. No visitor, address or identifier is ever stored. Plus the
 * reports staff have sent by email on a schedule.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_view_days', function (Blueprint $table): void {
            $table->id();
            $table->date('date');
            $table->string('locale', 8);
            $table->string('path', 300);
            $table->string('section', 40);
            $table->unsignedInteger('views')->default(0);

            $table->unique(['date', 'locale', 'path']);
            $table->index(['date', 'section']);
        });

        Schema::create('traffic_days', function (Blueprint $table): void {
            $table->id();
            $table->date('date');
            $table->string('locale', 8);
            $table->string('dimension', 20);
            $table->string('value', 120);
            $table->unsignedInteger('views')->default(0);

            $table->unique(['date', 'locale', 'dimension', 'value']);
            $table->index(['dimension', 'date']);
        });

        Schema::create('analytics_reports', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('dimension', 20);
            $table->string('locale_filter', 8)->nullable();
            $table->string('frequency', 10);
            $table->json('recipients');
            $table->string('mail_locale', 8)->default('en');
            $table->timestamp('last_sent_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_reports');
        Schema::dropIfExists('traffic_days');
        Schema::dropIfExists('page_view_days');
    }
};
