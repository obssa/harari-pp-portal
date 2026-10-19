<?php

namespace App\Filament\Resources\Activities\Schemas;

use App\Filament\Support\CalendarDate;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Spatie\Activitylog\Models\Activity;

class ActivityInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('admin.activity.section.what'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('created_at')->label(__('admin.activity.field.when'))->formatStateUsing(CalendarDate::format(withTime: true)),
                        TextEntry::make('log_name')->label(__('admin.activity.field.log'))->badge(),
                        TextEntry::make('event')->label(__('admin.activity.field.event')),
                        TextEntry::make('causer.name')
                            ->label(__('admin.activity.field.causer'))
                            ->placeholder(__('admin.activity.field.system')),
                        TextEntry::make('subject')
                            ->label(__('admin.activity.field.subject'))
                            ->state(fn (Activity $record): ?string => $record->subject_type
                                ? class_basename($record->subject_type).' #'.$record->subject_id : null)
                            ->placeholder('—'),
                        TextEntry::make('ip')
                            ->label(__('admin.activity.field.ip'))
                            ->state(fn (Activity $record): ?string => $record->getExtraProperty('ip'))
                            ->placeholder('—'),
                    ]),
                Section::make(__('admin.activity.section.changes'))
                    ->columns(2)
                    ->visible(fn (Activity $record): bool => filled($record->getExtraProperty('attributes'))
                        || filled($record->getExtraProperty('old')))
                    ->schema([
                        KeyValueEntry::make('old')
                            ->label(__('admin.activity.field.before'))
                            ->state(fn (Activity $record): array => self::flatten($record->getExtraProperty('old'))),
                        KeyValueEntry::make('new')
                            ->label(__('admin.activity.field.after'))
                            ->state(fn (Activity $record): array => self::flatten($record->getExtraProperty('attributes'))),
                    ]),
                Section::make(__('admin.activity.section.details'))
                    ->collapsed()
                    ->schema([
                        KeyValueEntry::make('details')
                            ->hiddenLabel()
                            ->state(fn (Activity $record): array => self::flatten(
                                $record->properties->except(['old', 'attributes'])->all(),
                            )),
                    ]),
            ]);
    }

    /**
     * @return array<string, string>
     */
    private static function flatten(mixed $values): array
    {
        $flat = [];
        foreach ((array) $values as $key => $value) {
            $flat[(string) $key] = is_scalar($value) || $value === null
                ? (string) ($value ?? '∅')
                : (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return $flat;
    }
}
