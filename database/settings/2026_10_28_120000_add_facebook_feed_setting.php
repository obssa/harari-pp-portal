<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // ADR-0071: the Facebook page's posts show once the visitor agrees, until an administrator chooses otherwise.
        $this->migrator->add('site.facebook_feed', 'ask');
    }
};
