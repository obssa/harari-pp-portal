<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The party's name in the region is "Harari Region Prosperity Party"
 * (ADR-0069); the default site keeps any name an admin gave it.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('sites')->where('name', 'Harari Prosperity Party')->update(['name' => 'Harari Region Prosperity Party', 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('sites')->where('name', 'Harari Region Prosperity Party')->update(['name' => 'Harari Prosperity Party', 'updated_at' => now()]);
    }
};
