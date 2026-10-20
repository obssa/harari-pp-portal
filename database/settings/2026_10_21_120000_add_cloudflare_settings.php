<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // ADR-0039: Cloudflare cache purges stay off until a zone and an API token are saved.
        $this->migrator->add('integrations.cloudflare_zone_id', null);
        $this->migrator->addEncrypted('integrations.cloudflare_api_token', null);
    }
};
