<?php

namespace App\Filament\Resources\Webhooks;

use App\Domain\Webhooks\Models\Webhook;
use App\Domain\Webhooks\Support\WebhookUrl;
use App\Filament\Resources\Webhooks\Pages\ManageWebhooks;
use App\Filament\Support\CalendarDate;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use UnitEnum;

/**
 * Outgoing webhooks (ADR-0013), managed by Super Admins with the other
 * integrations. The secret signs every request and is shown only once.
 */
class WebhookResource extends Resource
{
    protected static ?string $model = Webhook::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static ?int $navigationSort = 60;

    public static function getModelLabel(): string
    {
        return __('admin.webhooks.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.webhooks.plural');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.nav.administration');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label(__('admin.webhooks.field.name'))
                ->required()
                ->maxLength(120),
            TextInput::make('url')
                ->label(__('admin.webhooks.field.url'))
                ->helperText(__('admin.webhooks.help.url'))
                ->url()
                ->required()
                ->maxLength(500)
                ->rules([
                    fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                        if (! is_string($value) || ! WebhookUrl::isAllowed($value)) {
                            $fail(__('admin.webhooks.url_not_allowed'));
                        }
                    },
                ]),
            CheckboxList::make('events')
                ->label(__('admin.webhooks.field.events'))
                ->options(array_combine(Webhook::EVENTS, array_map(fn (string $e): string => __('admin.webhooks.event.'.str_replace('.', '_', $e)), Webhook::EVENTS)))
                ->required(),
            Toggle::make('is_active')
                ->label(__('admin.webhooks.field.active'))
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('admin.webhooks.field.name')),
                TextColumn::make('url')->label(__('admin.webhooks.field.url'))->limit(50),
                IconColumn::make('is_active')->label(__('admin.webhooks.field.active'))->boolean(),
                TextColumn::make('last_delivered_at')
                    ->label(__('admin.webhooks.field.last_delivered'))
                    ->formatStateUsing(CalendarDate::format(withTime: true))
                    ->placeholder('—'),
            ])
            ->recordActions([
                Action::make('deliveries')
                    ->label(__('admin.webhooks.action.deliveries'))
                    ->icon(Heroicon::OutlinedListBullet)
                    ->color('gray')
                    ->modalSubmitAction(false)
                    ->modalContent(fn (Webhook $record): View => view('filament.webhooks.deliveries', [
                        'deliveries' => $record->deliveries()->latest('id')->limit(25)->get(),
                    ])),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageWebhooks::route('/'),
        ];
    }
}
