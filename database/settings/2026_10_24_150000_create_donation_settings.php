<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // ADR-0060: donations stay off until a Super Admin switches them on after legal approval.
        $this->migrator->add('donations.enabled', false);
        $this->migrator->add('donations.intro', []);
        $this->migrator->add('donations.instructions', []);
        $this->migrator->add('donations.accounts', []);
        $this->migrator->add('donations.minimum', 10);
        $this->migrator->add('donations.currency', 'ETB');
    }
};
