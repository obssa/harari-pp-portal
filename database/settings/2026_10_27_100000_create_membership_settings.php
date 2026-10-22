<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // ADR-0067: applications are confirmed by SMS code until an administrator chooses otherwise.
        $this->migrator->add('membership.verification', 'sms');
    }
};
