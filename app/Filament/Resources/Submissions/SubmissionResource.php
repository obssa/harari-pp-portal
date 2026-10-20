<?php

namespace App\Filament\Resources\Submissions;

use App\Domain\Forms\Enums\SubmissionStatus;
use App\Domain\Forms\Models\Form;
use App\Domain\Forms\Models\FormSubmission;
use App\Domain\Forms\Support\AnswerText;
use App\Domain\Forms\Support\InboxScope;
use App\Domain\Translation\Support\Locales;
use App\Filament\Resources\Submissions\Pages\ListSubmissions;
use App\Filament\Resources\Submissions\Pages\ViewSubmission;
use App\Filament\Resources\Submissions\RelationManagers\NotesRelationManager;
use App\Filament\Support\CalendarDate;
use App\Filament\Support\CurrentUser;
use App\Models\User;
use App\Services\Calendar\EthiopianCalendar;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * The forms inbox (PLAN.md §7, ADR-0028): every ticket of the offices one
 * looks after, with its status, assignee and response clock.
 */
class SubmissionResource extends Resource
{
    protected static ?string $model = FormSubmission::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static ?int $navigationSort = 30;

    protected static ?string $slug = 'submissions';

    public static function getModelLabel(): string
    {
        return __('forms.inbox.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('forms.inbox.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('forms.inbox.title');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('forms.admin.nav');
    }

    public static function getNavigationBadge(): ?string
    {
        $user = Filament::auth()->user();
        if (! $user instanceof User || ! $user->can('inbox.view')) {
            return null;
        }
        $open = InboxScope::apply(FormSubmission::query(), $user)->where('status', '!=', SubmissionStatus::Resolved->value)->count();

        return $open > 0 ? (string) $open : null;
    }

    /**
     * @return Builder<FormSubmission>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<FormSubmission> $query */
        $query = parent::getEloquentQuery();

        return InboxScope::apply($query, CurrentUser::get());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['form.translations', 'office.translations', 'assignee']))
            ->columns([
                TextColumn::make('reference')->label(__('forms.reference'))->fontFamily('mono')->searchable()->copyable(),
                TextColumn::make('form')->label(__('forms.form'))->state(fn (FormSubmission $record): string => $record->form->title())->wrap(),
                TextColumn::make('status')->label(__('forms.status_label'))->badge()
                    ->formatStateUsing(fn (SubmissionStatus $state): string => $state->label())
                    ->color(fn (SubmissionStatus $state): string => $state->color()),
                TextColumn::make('office')->label(__('forms.admin.field.office'))->state(fn (FormSubmission $record): ?string => $record->office?->name())->placeholder('—')->toggleable(),
                TextColumn::make('assignee.name')->label(__('forms.inbox.assignee'))->placeholder('—'),
                TextColumn::make('created_at')->label(__('forms.sent_on'))->formatStateUsing(CalendarDate::format(withTime: true))->sortable(),
                TextColumn::make('due_at')->label(__('forms.inbox.clock'))
                    ->state(fn (FormSubmission $record): string => self::clock($record))
                    ->color(fn (FormSubmission $record): ?string => $record->isOverdue() ? 'danger' : ($record->first_response_at ? 'success' : null))
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('forms.status_label'))->options(SubmissionStatus::options()),
                SelectFilter::make('form_id')->label(__('forms.form'))
                    ->options(fn (): array => Form::query()->withTrashed()->with('translations')->get()->mapWithKeys(fn (Form $f): array => [$f->id => $f->title()])->all()),
                Filter::make('open')->label(__('forms.inbox.filter.open'))->default()
                    ->query(fn (Builder $query): Builder => $query->where('status', '!=', SubmissionStatus::Resolved->value)),
                Filter::make('mine')->label(__('forms.inbox.filter.mine'))
                    ->query(fn (Builder $query): Builder => $query->where('assignee_id', CurrentUser::get()->id)),
                Filter::make('overdue')->label(__('forms.inbox.filter.overdue'))
                    ->query(fn (Builder $query): Builder => $query->where('status', '!=', SubmissionStatus::Resolved->value)
                        ->whereNull('first_response_at')->where('due_at', '<', CarbonImmutable::now('UTC'))),
            ])
            ->recordActions([ViewAction::make()])
            ->recordUrl(fn (FormSubmission $record): string => self::getUrl('view', ['record' => $record]))
            ->poll('60s')
            ->emptyStateHeading(__('forms.inbox.empty'));
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Grid::make(1)->columnSpan(['default' => 3, 'lg' => 2])->schema([
                Section::make(__('forms.inbox.person'))->columns(3)->schema([
                    TextEntry::make('name')->label(__('events.registration.field.name'))->placeholder(__('forms.inbox.anonymous')),
                    TextEntry::make('email')->label(__('events.registration.field.email'))->placeholder('—')->copyable(),
                    TextEntry::make('phone')->label(__('events.registration.field.phone'))->placeholder('—')->copyable(),
                ]),
                Section::make(__('forms.inbox.answers'))->schema(fn (?FormSubmission $record): array => $record === null ? [] : self::answerEntries($record)),
            ]),
            Section::make(__('admin.posts.section.details'))->columnSpan(['default' => 3, 'lg' => 1])->schema([
                TextEntry::make('reference')->label(__('forms.reference'))->fontFamily('mono')->copyable(),
                TextEntry::make('form_title')->label(__('forms.form'))->state(fn (FormSubmission $record): string => $record->form->title()),
                TextEntry::make('status')->label(__('forms.status_label'))->badge()
                    ->formatStateUsing(fn (SubmissionStatus $state): string => $state->label())->color(fn (SubmissionStatus $state): string => $state->color()),
                TextEntry::make('office_name')->label(__('forms.admin.field.office'))->state(fn (FormSubmission $record): ?string => $record->office?->name())->placeholder('—'),
                TextEntry::make('assignee.name')->label(__('forms.inbox.assignee'))->placeholder('—'),
                TextEntry::make('locale')->label(__('admin.revisions.field.language'))->formatStateUsing(fn (string $state): string => Locales::nativeName($state)),
                TextEntry::make('created_at')->label(__('forms.sent_on'))->formatStateUsing(CalendarDate::format(withTime: true)),
                TextEntry::make('clock')->label(__('forms.inbox.clock'))->state(fn (FormSubmission $record): string => self::clock($record))
                    ->color(fn (FormSubmission $record): ?string => $record->isOverdue() ? 'danger' : null),
                TextEntry::make('resolved_at')->label(__('forms.inbox.resolved_at'))->formatStateUsing(CalendarDate::format(withTime: true))->placeholder('—'),
            ]),
        ]);
    }

    /**
     * @return list<TextEntry>
     */
    public static function answerEntries(FormSubmission $record): array
    {
        $entries = [];
        $files = collect($record->files ?? []);
        foreach ($record->form->questions() as $question) {
            $value = $record->answers[$question->key] ?? null;
            $file = $files->firstWhere('field', $question->key);
            $entries[] = TextEntry::make("answer_{$question->key}")
                ->label($question->labelFor(app()->getLocale()))
                ->state($file !== null ? __('forms.inbox.attachment', ['name' => $file['name']]) : AnswerText::for($value))
                ->placeholder('—')
                ->extraAttributes(['class' => 'whitespace-pre-line']);
        }

        return $entries;
    }

    /** "Answered …", "Answer by …" or "Overdue since …". */
    public static function clock(FormSubmission $record): string
    {
        $timezone = EthiopianCalendar::timezone();
        if ($record->first_response_at !== null) {
            $hours = (int) round($record->created_at->diffInHours($record->first_response_at));

            return __('forms.inbox.answered_after', ['hours' => $hours]);
        }
        if ($record->due_at === null || ! $record->status->isOpen()) {
            return '—';
        }

        return $record->due_at->isPast()
            ? __('forms.inbox.overdue_since', ['date' => $record->due_at->setTimezone($timezone)->format('Y-m-d')])
            : __('forms.inbox.answer_by', ['date' => $record->due_at->setTimezone($timezone)->format('Y-m-d')]);
    }

    public static function getRelations(): array
    {
        return [NotesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSubmissions::route('/'),
            'view' => ViewSubmission::route('/{record}'),
        ];
    }
}
