<?php

namespace App\Filament\Resources\Campaigns;

use App\Domain\Engagement\Actions\SendCampaign;
use App\Domain\Engagement\Enums\CampaignStatus;
use App\Domain\Engagement\Models\Campaign;
use App\Domain\Engagement\Models\CampaignDelivery;
use App\Domain\Engagement\Support\Audience;
use App\Domain\Engagement\Support\CampaignReport;
use App\Domain\Engagement\Support\SmsLength;
use App\Domain\Engagement\Support\SubscriptionOptions;
use App\Domain\Membership\Enums\MemberStatus;
use App\Domain\Organisation\Enums\OfficeType;
use App\Domain\Translation\Support\Locales;
use App\Filament\Resources\Campaigns\Pages\CreateCampaign;
use App\Filament\Resources\Campaigns\Pages\EditCampaign;
use App\Filament\Resources\Campaigns\Pages\ListCampaigns;
use App\Filament\Resources\Campaigns\Pages\ViewCampaign;
use App\Filament\Resources\Members\MemberResource;
use App\Filament\Support\CalendarDate;
use App\Filament\Support\CurrentUser;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Email and SMS campaigns (PLAN.md §5, ADR-0052): written per language as a
 * draft, sent to an audience now or at a set time, with what happened to
 * each message.
 */
class CampaignResource extends Resource
{
    protected static ?string $model = Campaign::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?int $navigationSort = 10;

    public static function getModelLabel(): string
    {
        return __('campaigns.admin.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('campaigns.admin.plural');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.nav.communication');
    }

    public static function form(Schema $schema): Schema
    {
        $audience = new Audience(CurrentUser::get());
        $isEmail = fn (Get $get): bool => $get('channel') !== Campaign::SMS;
        $type = fn (string ...$types): \Closure => fn (Get $get): bool => in_array($get('audience.type'), $types, true);

        return $schema->columns(1)->components([
            Section::make(__('campaigns.admin.section.campaign'))->columns(2)->schema([
                TextInput::make('name')->label(__('campaigns.field.name'))->helperText(__('campaigns.help.name'))->required()->maxLength(120),
                Radio::make('channel')->label(__('campaigns.field.channel'))->required()->live()->inline()->default(Campaign::EMAIL)
                    ->options([Campaign::EMAIL => __('newsletter.channel.email'), Campaign::SMS => __('newsletter.channel.sms')]),
            ]),
            Section::make(__('campaigns.admin.section.audience'))->columns(2)->schema([
                Select::make('audience.type')->label(__('campaigns.field.audience'))->required()->live()->options($audience->options())->default('subscribers'),
                CheckboxList::make('audience.topics')->label(__('campaigns.field.topics'))->helperText(__('campaigns.help.topics'))
                    ->options(SubscriptionOptions::topics())->visible($type('subscribers')),
                CheckboxList::make('audience.locales')->label(__('campaigns.field.locales'))->helperText(__('campaigns.help.locales'))
                    ->options(collect(Locales::all())->mapWithKeys(fn (string $l): array => [$l => Locales::nativeName($l)])->all())->visible($type('subscribers')),
                Select::make('audience.woredas')->label(__('campaigns.field.woredas'))->helperText(__('campaigns.help.woredas'))->multiple()->searchable()
                    ->options(fn (): array => MemberResource::offices(OfficeType::Woreda))->visible($type('members', 'volunteers')),
                CheckboxList::make('audience.statuses')->label(__('campaigns.field.statuses'))->helperText(__('campaigns.help.statuses'))
                    ->options(collect(MemberStatus::cases())->mapWithKeys(fn (MemberStatus $s): array => [$s->value => $s->label()])->all())->visible($type('members')),
            ]),
            Section::make(__('campaigns.admin.section.text'))->description(__('campaigns.help.languages'))->schema([
                Tabs::make('content')->tabs(array_map(fn (string $locale): Tab => Tab::make(Locales::nativeName($locale))->schema([
                    TextInput::make("content.{$locale}.subject")->label(__('campaigns.field.subject'))->maxLength(150)->visible($isEmail),
                    MarkdownEditor::make("content.{$locale}.body")->label(__('campaigns.field.body'))->maxLength(20000)->visible($isEmail)
                        ->toolbarButtons([['bold', 'italic', 'link'], ['heading', 'bulletList', 'orderedList'], ['undo', 'redo']]),
                    Textarea::make("content.{$locale}.text")->label(__('campaigns.field.sms'))->rows(4)->live(debounce: 500)
                        ->visible(fn (Get $get): bool => ! $isEmail($get))
                        ->helperText(fn (?string $state): string => self::smsHelp((string) $state, $locale)),
                ]), Locales::all())),
            ]),
        ]);
    }

    public static function smsHelp(string $text, string $locale): string
    {
        $length = SmsLength::measure(trim($text).' '.SendCampaign::stopLine($locale, str_repeat('x', 12)));

        return __('campaigns.help.sms', ['characters' => $length['characters'], 'parts' => $length['parts'], 'max' => SmsLength::MAX_PARTS]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->withCount([
                'deliveries as sent_count' => fn ($q) => $q->where('status', CampaignDelivery::SENT),
                'deliveries',
            ]))
            ->columns([
                TextColumn::make('name')->label(__('campaigns.field.name'))->searchable()->wrap(),
                TextColumn::make('channel')->label(__('campaigns.field.channel'))->badge()
                    ->formatStateUsing(fn (string $state): string => __("newsletter.channel.{$state}")),
                TextColumn::make('audience_type')->label(__('campaigns.field.audience'))
                    ->state(fn (Campaign $record): string => __('campaigns.audience.'.($record->audience['type'] ?? 'subscribers'))),
                TextColumn::make('status')->label(__('membership.admin.status'))->badge()
                    ->formatStateUsing(fn (CampaignStatus $state): string => $state->label())->color(fn (CampaignStatus $state): string => $state->color()),
                TextColumn::make('when')->label(__('campaigns.field.when'))
                    ->state(fn (Campaign $record) => $record->started_at ?? $record->scheduled_at)->formatStateUsing(CalendarDate::format(withTime: true))->placeholder('—'),
                TextColumn::make('sent_count')->label(__('campaigns.field.sent'))
                    ->state(fn (Campaign $record): string => $record->deliveries_count > 0 ? "{$record->sent_count} / {$record->deliveries_count}" : '—'),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('membership.admin.status'))
                    ->options(collect(CampaignStatus::cases())->mapWithKeys(fn (CampaignStatus $s): array => [$s->value => $s->label()])->all()),
                SelectFilter::make('channel')->label(__('campaigns.field.channel'))
                    ->options([Campaign::EMAIL => __('newsletter.channel.email'), Campaign::SMS => __('newsletter.channel.sms')]),
            ])
            ->recordActions([ViewAction::make()])
            ->recordUrl(fn (Campaign $record): string => self::getUrl('view', ['record' => $record]))
            ->emptyStateHeading(__('campaigns.admin.empty'));
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make(__('campaigns.admin.section.text'))->columnSpan(['default' => 3, 'lg' => 2])->schema(fn (?Campaign $record): array => $record === null ? [] : [
                Tabs::make('preview')->tabs(array_map(fn (string $locale): Tab => Tab::make(Locales::nativeName($locale))->schema([
                    TextEntry::make("preview_subject_{$locale}")->label(__('campaigns.field.subject'))
                        ->state($record->content[$locale]['subject'] ?? null)->visible($record->channel === Campaign::EMAIL),
                    TextEntry::make("preview_body_{$locale}")->label(__('campaigns.field.body'))
                        ->state(new HtmlString(Str::markdown((string) ($record->content[$locale]['body'] ?? ''), ['html_input' => 'strip', 'allow_unsafe_links' => false])))
                        ->visible($record->channel === Campaign::EMAIL),
                    TextEntry::make("preview_sms_{$locale}")->label(__('campaigns.field.sms'))
                        ->state($record->content[$locale]['text'] ?? null)->helperText(self::smsHelp((string) ($record->content[$locale]['text'] ?? ''), $locale))
                        ->visible($record->channel === Campaign::SMS),
                ]), $record->languages() !== [] ? $record->languages() : ['en'])),
            ]),
            Grid::make(1)->columnSpan(['default' => 3, 'lg' => 1])->schema([
                Section::make(__('campaigns.admin.section.campaign'))->schema([
                    TextEntry::make('status')->label(__('membership.admin.status'))->badge()
                        ->formatStateUsing(fn (CampaignStatus $state): string => $state->label())->color(fn (CampaignStatus $state): string => $state->color()),
                    TextEntry::make('channel')->label(__('campaigns.field.channel'))->formatStateUsing(fn (string $state): string => __("newsletter.channel.{$state}")),
                    TextEntry::make('audience_summary')->label(__('campaigns.field.audience'))
                        ->state(fn (Campaign $record): string => __('campaigns.audience.'.($record->audience['type'] ?? 'subscribers'))),
                    TextEntry::make('reach')->label(__('campaigns.field.reach'))
                        ->state(fn (Campaign $record): int => (new Audience(CurrentUser::get()))->count($record))
                        ->visible(fn (Campaign $record): bool => in_array($record->status, [CampaignStatus::Draft, CampaignStatus::Scheduled], true)),
                    TextEntry::make('languages')->label(__('campaigns.field.languages'))
                        ->state(fn (Campaign $record): string => implode(', ', array_map(fn (string $l): string => Locales::nativeName($l), $record->languages())) ?: '—'),
                    TextEntry::make('scheduled_at')->label(__('campaigns.field.scheduled_at'))->formatStateUsing(CalendarDate::format(withTime: true))->placeholder('—'),
                    TextEntry::make('started_at')->label(__('campaigns.field.started_at'))->formatStateUsing(CalendarDate::format(withTime: true))->placeholder('—'),
                    TextEntry::make('finished_at')->label(__('campaigns.field.finished_at'))->formatStateUsing(CalendarDate::format(withTime: true))->placeholder('—'),
                    TextEntry::make('sender.name')->label(__('campaigns.field.sent_by'))->placeholder('—'),
                ]),
                Section::make(__('campaigns.report.title'))
                    ->visible(fn (Campaign $record): bool => in_array($record->status, [CampaignStatus::Sending, CampaignStatus::Sent, CampaignStatus::Cancelled], true))
                    ->schema(fn (?Campaign $record): array => $record === null ? [] : self::reportEntries($record)),
            ]),
        ]);
    }

    /**
     * @return list<TextEntry>
     */
    private static function reportEntries(Campaign $campaign): array
    {
        $report = new CampaignReport($campaign);
        $totals = $report->totals();
        $rate = fn (int $part): string => $part.' ('.CampaignReport::rate($part, $totals['sent']).'%)';
        $entries = [
            TextEntry::make('report_recipients')->label(__('campaigns.report.recipients'))->state($totals['recipients']),
            TextEntry::make('report_sent')->label(__('campaigns.report.sent'))->state($totals['sent'])->color('success'),
            TextEntry::make('report_queued')->label(__('campaigns.report.queued'))->state($totals['queued'])->visible($totals['queued'] > 0),
            TextEntry::make('report_failed')->label(__('campaigns.report.failed'))->state($totals['failed'])->color($totals['failed'] > 0 ? 'danger' : null),
            TextEntry::make('report_skipped')->label(__('campaigns.report.skipped'))->state($totals['skipped'])->helperText(__('campaigns.report.skipped_help')),
            TextEntry::make('report_clicked')->label(__('campaigns.report.clicked'))->state($rate($totals['clicked']))
                ->helperText(__('campaigns.report.clicks', ['count' => $totals['clicks']]))->visible($campaign->channel === Campaign::EMAIL),
            TextEntry::make('report_opened')->label(__('campaigns.report.opened'))->state($rate($totals['opened']))
                ->visible($campaign->channel === Campaign::EMAIL && ($totals['opened'] > 0 || (bool) config('portal.campaign_open_tracking'))),
            TextEntry::make('report_unsubscribed')->label(__('campaigns.report.unsubscribed'))->state($rate($totals['unsubscribed'])),
            TextEntry::make('report_languages')->label(__('campaigns.report.by_language'))->listWithLineBreaks()
                ->state(array_map(fn (array $row): string => __('campaigns.report.language_line', [
                    'language' => Locales::nativeName($row['language']), 'sent' => $row['sent'], 'recipients' => $row['recipients'], 'clicked' => $row['clicked'],
                ]), $report->byLanguage())),
        ];

        return $entries;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCampaigns::route('/'),
            'create' => CreateCampaign::route('/create'),
            'view' => ViewCampaign::route('/{record}'),
            'edit' => EditCampaign::route('/{record}/edit'),
        ];
    }
}
