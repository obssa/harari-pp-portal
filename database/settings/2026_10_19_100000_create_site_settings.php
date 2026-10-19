<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('site.default_locale', 'am');
        $this->migrator->add('site.calendar_primary', 'ethiopian');
        $this->migrator->add('site.show_secondary_calendar', true);
        $this->migrator->add('site.contact_email', null);
        $this->migrator->add('site.contact_phone', null);
        $this->migrator->add('site.hotline', null);
        $this->migrator->add('site.address', []);
        $this->migrator->add('site.office_hours', []);
        $this->migrator->add('site.social_links', []);
    }
};
