<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // ADR-0058: Fayda sign-in stays off until the branch office is registered with eSignet.
        $this->migrator->add('integrations.fayda_enabled', false);
        $this->migrator->add('integrations.fayda_issuer', null);
        $this->migrator->add('integrations.fayda_client_id', null);
        $this->migrator->add('integrations.fayda_acr_values', 'mosip:idp:acr:generated-code');
        $this->migrator->addEncrypted('integrations.fayda_private_key', null);
    }
};
