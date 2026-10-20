<?php

namespace App\Filament\Pages;

use App\Filament\Support\CurrentUser;
use App\Settings\IntegrationSettings;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * API keys and service credentials (PLAN.md §7, §8: Super Admin only).
 * Saved secrets are encrypted, never shown again, and kept unless a new
 * value is typed or "remove" is ticked.
 */
class ManageIntegrations extends SettingsPage
{
    protected static string $settings = IntegrationSettings::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?string $slug = 'settings/integrations';

    protected static ?int $navigationSort = 41;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.nav.administration');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.settings.integrations.title');
    }

    public function getTitle(): string
    {
        return __('admin.settings.integrations.title');
    }

    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->can('integrations.manage');
    }

    public function canEdit(): bool
    {
        return CurrentUser::get()->can('integrations.manage');
    }

    public function form(Schema $schema): Schema
    {
        $settings = app(IntegrationSettings::class);

        return $schema
            ->columns(1)
            ->components([
                Section::make(__('admin.settings.integrations.section.sms'))
                    ->columns(3)
                    ->schema([
                        Select::make('sms_driver')
                            ->label(__('admin.settings.integrations.field.sms_driver'))
                            ->options([
                                'log' => __('admin.settings.integrations.field.sms_driver_log'),
                                'ethiotelecom' => 'Ethio Telecom',
                                'africastalking' => "Africa's Talking",
                                'twilio' => 'Twilio',
                            ])
                            ->required(),
                        $this->secret('sms_api_key', $settings),
                        TextInput::make('sms_sender_id')
                            ->label(__('admin.settings.integrations.field.sms_sender_id'))
                            ->maxLength(20),
                    ]),
                Section::make(__('admin.settings.integrations.section.translation'))
                    ->columns(3)
                    ->description(__('translations.machine.settings_help'))
                    ->schema([
                        Select::make('mt_driver')
                            ->label(__('translations.machine.driver'))
                            ->options([
                                'none' => __('translations.machine.driver_none'),
                                'google' => 'Google Cloud Translation',
                                'llm' => __('translations.machine.driver_llm'),
                            ])
                            ->required()
                            ->columnSpanFull(),
                        $this->secret('google_translate_api_key', $settings),
                        Select::make('llm_provider')
                            ->label(__('admin.settings.integrations.field.llm_provider'))
                            ->options(['anthropic' => 'Anthropic', 'openai' => 'OpenAI'])
                            ->required(),
                        $this->secret('llm_api_key', $settings),
                        TextInput::make('llm_model')
                            ->label(__('translations.machine.model'))
                            ->helperText(__('translations.machine.model_help'))
                            ->maxLength(100),
                    ]),
                Section::make(__('admin.settings.integrations.section.social'))
                    ->columns(3)
                    ->schema([
                        $this->secret('meta_page_access_token', $settings),
                        $this->secret('youtube_api_key', $settings),
                        $this->secret('telegram_bot_token', $settings),
                    ]),
                Section::make(__('admin.settings.integrations.section.spam'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('turnstile_site_key')
                            ->label(__('admin.settings.integrations.field.turnstile_site_key'))
                            ->maxLength(255),
                        $this->secret('turnstile_secret_key', $settings),
                    ]),
                Section::make(__('admin.settings.integrations.section.cdn'))
                    ->description(__('admin.settings.integrations.cdn_help'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('cloudflare_zone_id')
                            ->label(__('admin.settings.integrations.field.cloudflare_zone_id'))
                            ->maxLength(64)
                            ->regex('/^[a-f0-9]{32}$/'),
                        $this->secret('cloudflare_api_token', $settings),
                    ]),
                Section::make(__('admin.settings.integrations.section.remove'))
                    ->collapsed()
                    ->schema([
                        CheckboxList::make('remove_secrets')
                            ->hiddenLabel()
                            ->options(collect(IntegrationSettings::secretFields())
                                ->filter(fn (string $f): bool => $settings->isConfigured($f))
                                ->mapWithKeys(fn (string $f): array => [$f => __('admin.settings.integrations.field.'.$f)])
                                ->all())
                            ->dehydrated(false),
                    ]),
            ]);
    }

    /**
     * Secrets are never sent back to the browser.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        foreach (IntegrationSettings::secretFields() as $field) {
            $data[$field] = null;
        }

        return $data;
    }

    /**
     * An empty secret field keeps the saved value; ticked ones are removed.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $remove = (array) ($this->form->getRawState()['remove_secrets'] ?? []);

        foreach (IntegrationSettings::secretFields() as $field) {
            if (in_array($field, $remove, true)) {
                $data[$field] = null;
            } elseif (blank($data[$field] ?? null)) {
                unset($data[$field]);
            }
        }

        return $data;
    }

    private function secret(string $field, IntegrationSettings $settings): TextInput
    {
        return TextInput::make($field)
            ->label(__('admin.settings.integrations.field.'.$field))
            ->password()
            ->autocomplete('new-password')
            ->maxLength(2048)
            ->placeholder($settings->isConfigured($field) ? '••••••••' : null)
            ->helperText($settings->isConfigured($field)
                ? __('admin.settings.integrations.saved_keep')
                : __('admin.settings.integrations.not_set'));
    }
}
