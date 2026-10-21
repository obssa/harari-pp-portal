<?php

namespace App\Filament\Pages;

use App\Domain\Privacy\Actions\ErasePersonalData;
use App\Domain\Privacy\Actions\ExportPersonalData;
use App\Domain\Privacy\Models\PrivacyRequest;
use App\Domain\Privacy\Support\PersonalDataSearch;
use App\Domain\Privacy\Support\RetentionRules;
use App\Filament\Support\CalendarDate;
use App\Filament\Support\CurrentUser;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * The privacy area (PLAN.md §11, Proclamation No. 1321/2024, ADR-0041): find
 * what the portal holds about a person by email or phone, give them a copy
 * or erase it, and see how long each kind of record is kept. Every request
 * is logged without the person's details.
 *
 * @property-read Schema $form
 */
class PrivacyRequests extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $slug = 'privacy';

    protected static ?int $navigationSort = 96;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public ?string $email = null;

    public ?string $phone = null;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.nav.administration');
    }

    public static function getNavigationLabel(): string
    {
        return __('privacy.title');
    }

    public function getTitle(): string
    {
        return __('privacy.title');
    }

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->can('privacy.manage');
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('email')->label(__('privacy.field.email'))->email()->maxLength(190),
            TextInput::make('phone')->label(__('privacy.field.phone'))->tel()->maxLength(30),
        ]);
    }

    public function find(): void
    {
        abort_unless(CurrentUser::get()->can('privacy.manage'), 403);
        $state = $this->form->getState();
        $search = new PersonalDataSearch($state['email'] ?? null, $state['phone'] ?? null);
        if (! $search->isValid()) {
            Notification::make()->warning()->title(__('privacy.invalid'))->send();
            $this->email = $this->phone = null;

            return;
        }
        $this->email = $search->email;
        $this->phone = $search->phone;
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label(__('privacy.action.export'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->visible(fn (): bool => $this->search() !== null)
                ->action(function (): StreamedResponse {
                    abort_unless(CurrentUser::get()->can('privacy.manage') && ($search = $this->search()) !== null, 403);
                    $json = (string) json_encode(app(ExportPersonalData::class)->handle($search, CurrentUser::get()), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    $this->resetTable();

                    return response()->streamDownload(function () use ($json): void {
                        echo $json;
                    }, 'personal-data-'.now()->format('Y-m-d').'.json', ['Content-Type' => 'application/json; charset=UTF-8']);
                }),
            Action::make('erase')
                ->label(__('privacy.action.erase'))
                ->icon(Heroicon::OutlinedTrash)
                ->color('danger')
                ->visible(fn (): bool => ($this->search()?->erasable() ?? 0) > 0)
                ->requiresConfirmation()
                ->modalHeading(__('privacy.action.erase_heading'))
                ->modalDescription(fn (): string => __('privacy.action.erase_help', $this->summary($this->search()?->counts() ?? [])))
                ->action(function (): void {
                    abort_unless(CurrentUser::get()->can('privacy.manage') && ($search = $this->search()) !== null, 403);
                    $found = app(ErasePersonalData::class)->handle($search, CurrentUser::get());
                    Notification::make()->success()->title(__('privacy.erased', $this->summary($found)))->send();
                    $this->resetTable();
                }),
        ];
    }

    public function content(Schema $schema): Schema
    {
        $search = $this->search();

        return $schema->components([
            Section::make(__('privacy.section.find'))
                ->description(__('privacy.help'))
                ->schema([
                    Form::make([EmbeddedSchema::make('form')])->id('form')->livewireSubmitHandler('find')
                        ->footer([Actions::make([Action::make('find')->label(__('privacy.action.find'))->icon(Heroicon::OutlinedMagnifyingGlass)->submit('find')])]),
                ]),
            Section::make(__('privacy.section.found'))
                ->visible($search !== null)
                ->schema($search === null ? [] : $this->found($search)),
            Section::make(__('privacy.section.retention'))
                ->description(__('privacy.retention.help'))
                ->collapsible()
                ->schema([Grid::make(['default' => 1, 'md' => 2])->schema(array_map(
                    fn (array $rule, int $i): TextEntry => TextEntry::make("rule_{$i}")->label($rule['data'])->state($rule['kept'])->belowContent($rule['enforced']),
                    RetentionRules::all(),
                    array_keys(RetentionRules::all()),
                ))]),
            EmbeddedTable::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => PrivacyRequest::query()->with('handler'))
            ->defaultSort('created_at', 'desc')
            ->heading(__('privacy.section.log'))
            ->columns([
                TextColumn::make('created_at')->label(__('privacy.column.when'))->formatStateUsing(CalendarDate::format(withTime: true)),
                TextColumn::make('kind')->label(__('privacy.column.kind'))->badge()
                    ->formatStateUsing(fn (string $state): string => $state === PrivacyRequest::ERASE ? __('privacy.kind.erase') : __('privacy.kind.export'))
                    ->color(fn (string $state): string => $state === PrivacyRequest::ERASE ? 'danger' : 'gray'),
                TextColumn::make('found')->label(__('privacy.column.found'))
                    ->formatStateUsing(fn (mixed $state, PrivacyRequest $record): string => __('privacy.found_summary', $this->summary($record->found ?? []))),
                TextColumn::make('handler.name')->label(__('privacy.column.by'))->placeholder('—'),
            ])
            ->emptyStateHeading(__('privacy.no_requests'));
    }

    /**
     * The numbers for the summary sentences; requests logged before ADR-0046 have no membership counts.
     *
     * @param  array<string, mixed>  $counts
     * @return array{registrations: int, submissions: int, applications: int, members: int}
     */
    private function summary(array $counts): array
    {
        return [
            'registrations' => (int) ($counts['registrations'] ?? 0),
            'submissions' => (int) ($counts['submissions'] ?? 0),
            'applications' => (int) ($counts['applications'] ?? 0),
            'members' => (int) ($counts['members'] ?? 0),
        ];
    }

    private function search(): ?PersonalDataSearch
    {
        $search = new PersonalDataSearch($this->email, $this->phone);

        return $search->isValid() ? $search : null;
    }

    /**
     * @return list<mixed>
     */
    private function found(PersonalDataSearch $search): array
    {
        $counts = $search->counts();

        return [
            Grid::make(['default' => 2, 'md' => 3])->schema([
                TextEntry::make('registrations')->label(__('privacy.found.registrations'))->state($counts['registrations']),
                TextEntry::make('submissions')->label(__('privacy.found.submissions'))->state($counts['submissions']),
                TextEntry::make('applications')->label(__('privacy.found.applications'))->state($counts['applications']),
                TextEntry::make('members')->label(__('privacy.found.members'))->state($counts['members']),
                TextEntry::make('failed')->label(__('privacy.found.failed_sign_ins'))->state($counts['failed_sign_ins']),
                TextEntry::make('staff')->label(__('privacy.found.staff_account'))
                    ->state($counts['staff_account'] > 0 ? __('privacy.found.staff_yes') : __('privacy.found.staff_no')),
            ]),
        ];
    }
}
