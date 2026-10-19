<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-site ready from day one (PLAN.md §7 Future-proofing): content tables
 * get a site_id in Phase 2. One site exists now.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('name');
            $table->string('domain')->nullable()->unique();
            $table->boolean('is_default')->default(false)->index();
            $table->timestamps();
        });

        DB::table('sites')->insert([
            'key' => 'harari',
            'name' => 'Harari Prosperity Party',
            'domain' => null,
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('sites');
    }
};
