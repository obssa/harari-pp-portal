<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Importing content from the branch's and the communication office's public
 * channels into drafts (docs/content/sources.md, ADR-0022).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_sources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 16);
            $table->string('handle', 64);
            $table->string('name', 160);
            $table->unsignedTinyInteger('priority')->default(1);
            $table->string('default_locale', 8)->default('am');
            $table->boolean('enabled')->default(true);
            $table->unsignedBigInteger('cursor')->nullable();
            $table->timestamp('last_fetched_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'platform', 'handle']);
        });

        Schema::create('content_imports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('content_source_id')->constrained()->cascadeOnDelete();
            $table->string('external_id', 120);
            $table->timestamp('message_at');
            $table->string('status', 24);
            $table->foreignUlid('post_id')->nullable()->constrained()->nullOnDelete();
            $table->text('fingerprint')->nullable();
            $table->timestamps();

            $table->unique(['content_source_id', 'external_id']);
            $table->index(['site_id', 'message_at']);
        });

        Schema::table('posts', function (Blueprint $table): void {
            $table->string('source_url', 300)->nullable();
            $table->string('source_name', 160)->nullable();
            $table->timestamp('source_published_at')->nullable();
        });

        // The two public channels named in docs/content/sources.md.
        $now = now();
        foreach (DB::table('sites')->pluck('id') as $siteId) {
            DB::table('content_sources')->insertOrIgnore([
                [
                    'site_id' => $siteId, 'platform' => 'telegram', 'handle' => 'hrprosperityparty',
                    'name' => 'Prosperity Party Harari Region Branch', 'priority' => 1, 'default_locale' => 'am',
                    'enabled' => true, 'created_at' => $now, 'updated_at' => $now,
                ],
                [
                    'site_id' => $siteId, 'platform' => 'telegram', 'handle' => 'HarariGovernmentCommunication',
                    'name' => 'Harari Government Communication Affairs Office', 'priority' => 2, 'default_locale' => 'am',
                    'enabled' => true, 'created_at' => $now, 'updated_at' => $now,
                ],
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            $table->dropColumn(['source_url', 'source_name', 'source_published_at']);
        });
        Schema::dropIfExists('content_imports');
        Schema::dropIfExists('content_sources');
    }
};
