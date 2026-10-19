<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UI strings edited in the admin (PLAN.md §6, §10). Values here override
 * lang/*.json; keys without a row use the shipped files.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ui_translations', function (Blueprint $table) {
            $table->id();
            // '*' for JSON keys (all portal UI strings); a file group such as 'validation' otherwise.
            $table->string('group', 64)->default('*')->index();
            $table->string('key', 191);
            $table->json('text');
            $table->text('context')->nullable();
            $table->string('screenshot_url', 2048)->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['group', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ui_translations');
    }
};
