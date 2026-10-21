<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ADR-0063: the content pack loads each record once, remembered here by
     * its key; leaders carry the public source of their position; the
     * branch's own channel publishes its imported stories straight away.
     */
    public function up(): void
    {
        Schema::create('content_pack_items', function (Blueprint $table) {
            $table->id();
            $table->string('key', 120)->unique();
            $table->string('model_type', 120)->nullable();
            $table->string('model_id', 36)->nullable();
            $table->timestamp('loaded_at');
        });

        Schema::table('leaders', function (Blueprint $table) {
            $table->string('source_url', 500)->nullable()->after('socials');
            $table->date('source_date')->nullable()->after('source_url');
        });

        Schema::table('content_sources', function (Blueprint $table) {
            $table->boolean('auto_publish')->default(false)->after('enabled');
        });

        DB::table('content_sources')->where('handle', 'hrprosperityparty')->update(['auto_publish' => true]);
    }

    public function down(): void
    {
        Schema::table('content_sources', fn (Blueprint $table) => $table->dropColumn('auto_publish'));
        Schema::table('leaders', fn (Blueprint $table) => $table->dropColumn(['source_url', 'source_date']));
        Schema::dropIfExists('content_pack_items');
    }
};
