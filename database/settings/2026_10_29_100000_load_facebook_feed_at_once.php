<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // ADR-0072: the branch office wants the Facebook posts shown at once; a choice of "off" stays.
        $this->migrator->update('site.facebook_feed', fn (?string $mode): string => $mode === 'off' ? 'off' : 'auto');
    }
};
