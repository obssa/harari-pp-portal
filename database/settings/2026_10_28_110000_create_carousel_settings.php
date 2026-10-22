<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // ADR-0070: how the homepage carousel moves; editors change it under Content → Homepage carousel.
        $this->migrator->add('carousel.autoplay', true);
        $this->migrator->add('carousel.interval', 7);
        $this->migrator->add('carousel.transition', 'slide');
        $this->migrator->add('carousel.pause_on_hover', true);
        $this->migrator->add('carousel.show_arrows', true);
        $this->migrator->add('carousel.show_dots', true);
        $this->migrator->add('carousel.show_progress', true);
        $this->migrator->add('carousel.height', 'standard');
        $this->migrator->add('carousel.include_stories', true);
    }
};
