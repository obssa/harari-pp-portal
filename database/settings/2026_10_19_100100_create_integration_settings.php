<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('integrations.sms_driver', 'log');
        $this->migrator->addEncrypted('integrations.sms_api_key', null);
        $this->migrator->add('integrations.sms_sender_id', null);
        $this->migrator->addEncrypted('integrations.google_translate_api_key', null);
        $this->migrator->add('integrations.llm_provider', 'anthropic');
        $this->migrator->addEncrypted('integrations.llm_api_key', null);
        $this->migrator->addEncrypted('integrations.meta_page_access_token', null);
        $this->migrator->addEncrypted('integrations.youtube_api_key', null);
        $this->migrator->addEncrypted('integrations.telegram_bot_token', null);
        $this->migrator->add('integrations.turnstile_site_key', null);
        $this->migrator->addEncrypted('integrations.turnstile_secret_key', null);
    }
};
