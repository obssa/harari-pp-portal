<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // The press kit (PLAN.md §4 News & Media, ADR-0029): filled in by communications staff.
        $this->migrator->add('press.boilerplate', []);
        $this->migrator->add('press.contact_name', []);
        $this->migrator->add('press.contact_email', null);
        $this->migrator->add('press.contact_phone', null);
        $this->migrator->add('press.logo_ids', []);
        $this->migrator->add('press.form_id', null);
    }
};
