<?php

namespace App\Filament\Resources\HeroSlides\Pages;

use App\Filament\Resources\HeroSlides\HeroSlideResource;
use App\Filament\Support\CurrentUser;
use App\Filament\Support\ViewOnSite;
use App\Settings\CarouselSettings;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Grid;
use Filament\Support\Icons\Heroicon;

class ListHeroSlides extends ListRecords
{
    protected static string $resource = HeroSlideResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label(__('admin.carousel.new')),
            self::settingsAction(),
            ViewOnSite::site(),
        ];
    }

    /**
     * How the carousel moves (ADR-0070), for those who arrange the homepage.
     */
    public static function settingsAction(): Action
    {
        return Action::make('carouselSettings')
            ->label(__('admin.carousel.settings'))
            ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
            ->color('gray')
            ->modalHeading(__('admin.carousel.settings'))
            ->modalDescription(__('admin.carousel.settings_hint'))
            ->modalSubmitActionLabel(__('admin.carousel.save_settings'))
            ->authorize(fn (): bool => CurrentUser::get()->can('homepage.manage'))
            ->fillForm(fn (): array => CarouselSettings::current())
            ->schema([
                Toggle::make('autoplay')->label(__('admin.carousel.settings.autoplay'))->helperText(__('admin.carousel.settings.autoplay_hint')),
                Grid::make(2)->schema([
                    TextInput::make('interval')->label(__('admin.carousel.settings.interval'))
                        ->numeric()->integer()->minValue(CarouselSettings::MIN_INTERVAL)->maxValue(CarouselSettings::MAX_INTERVAL)
                        ->suffix(__('admin.carousel.seconds'))->required(),
                    Radio::make('transition')->label(__('admin.carousel.settings.transition'))
                        ->options(['slide' => __('admin.carousel.settings.transition_slide'), 'focus' => __('admin.carousel.settings.transition_focus')])
                        ->required(),
                ]),
                Radio::make('height')->label(__('admin.carousel.settings.height'))->inline()
                    ->options(collect(CarouselSettings::HEIGHTS)->mapWithKeys(fn (string $h): array => [$h => __("admin.carousel.settings.height_{$h}")])->all())
                    ->required(),
                Toggle::make('pause_on_hover')->label(__('admin.carousel.settings.pause_on_hover')),
                Toggle::make('show_arrows')->label(__('admin.carousel.settings.show_arrows')),
                Toggle::make('show_dots')->label(__('admin.carousel.settings.show_dots')),
                Toggle::make('show_progress')->label(__('admin.carousel.settings.show_progress')),
                Toggle::make('include_stories')->label(__('admin.carousel.settings.include_stories'))->helperText(__('admin.carousel.settings.include_stories_hint')),
            ])
            ->action(function (array $data): void {
                $settings = app(CarouselSettings::class);
                $settings->autoplay = (bool) ($data['autoplay'] ?? true);
                $settings->interval = max(CarouselSettings::MIN_INTERVAL, min(CarouselSettings::MAX_INTERVAL, (int) ($data['interval'] ?? 7)));
                $settings->transition = in_array($data['transition'] ?? null, CarouselSettings::TRANSITIONS, true) ? (string) $data['transition'] : 'slide';
                $settings->height = in_array($data['height'] ?? null, CarouselSettings::HEIGHTS, true) ? (string) $data['height'] : 'standard';
                $settings->pause_on_hover = (bool) ($data['pause_on_hover'] ?? true);
                $settings->show_arrows = (bool) ($data['show_arrows'] ?? true);
                $settings->show_dots = (bool) ($data['show_dots'] ?? true);
                $settings->show_progress = (bool) ($data['show_progress'] ?? true);
                $settings->include_stories = (bool) ($data['include_stories'] ?? true);
                $settings->save();

                Notification::make()->success()->title(__('admin.carousel.settings_saved'))->send();
            });
    }
}
