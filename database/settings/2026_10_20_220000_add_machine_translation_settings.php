<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // ADR-0035: machine translation stays off until a Super Admin chooses a driver.
        $this->migrator->add('integrations.mt_driver', 'none');
        $this->migrator->add('integrations.llm_model', null);
    }
};
