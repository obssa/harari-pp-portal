<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every change to an interface string, with the English text it was
 * translated from (ADR-0033): the history, and how "English changed since"
 * is found.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ui_translation_revisions', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 191);
            $table->string('locale', 8);
            $table->text('old_text')->nullable();
            $table->text('new_text')->nullable();
            $table->text('source_text')->nullable();
            $table->string('origin', 16)->default('editor');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['key', 'locale', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ui_translation_revisions');
    }
};
