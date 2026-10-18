<?php

namespace Database\Seeders;

use App\Domain\Access\Actions\SyncPermissions;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seeds what every environment needs. People are never seeded here:
     * use `portal:create-super-admin` (and `portal:demo-accounts` on staging).
     */
    public function run(SyncPermissions $sync): void
    {
        $sync();
    }
}
