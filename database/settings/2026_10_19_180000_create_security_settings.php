<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // Off at first, so nobody is locked out before they can set up the app (ADR-0018).
        $this->migrator->add('security.require_mfa', false);
    }
};
