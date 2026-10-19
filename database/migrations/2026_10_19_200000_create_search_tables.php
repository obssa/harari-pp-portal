<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

/**
 * Site search (ADR-0020): the text search matches against, on every kind of
 * translation, and the counts of what visitors searched for.
 */
return new class extends Migration
{
    private const TABLES = ['post_translations', 'page_translations', 'leader_translations', 'office_translations', 'league_translations'];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->mediumText('search_text')->nullable();
            });
        }

        Schema::create('search_terms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 8);
            $table->string('term', 100);
            $table->unsignedInteger('searches')->default(0);
            $table->unsignedInteger('last_results')->default(0);
            $table->timestamp('last_searched_at');
            $table->timestamps();

            $table->unique(['site_id', 'locale', 'term']);
            $table->index(['site_id', 'last_results']);
        });

        Artisan::call('portal:search-reindex');
    }

    public function down(): void
    {
        Schema::dropIfExists('search_terms');
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropColumn('search_text');
            });
        }
    }
};
