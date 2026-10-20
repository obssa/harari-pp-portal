<?php

namespace App\Filament\Pages;

use App\Domain\Health\Backups\BackupArchive;
use App\Domain\Health\Backups\RestoreDrill;
use App\Domain\Health\HealthChecks;
use App\Domain\Health\Mail\TestMail;
use App\Filament\Support\CalendarDate;
use App\Filament\Support\CurrentUser;
use App\Models\User;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Spatie\Health\Commands\RunHealthChecksCommand;
use Spatie\Health\ResultStores\ResultStore;
use Spatie\Health\ResultStores\StoredCheckResults\StoredCheckResult;
use Spatie\Health\ResultStores\StoredCheckResults\StoredCheckResults;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;
use UnitEnum;

/**
 * The state of the portal for administrators (PLAN.md §7 queue and system
 * health, backups, ADR-0037): the latest result of every health check, run
 * every five minutes; the backups on the server and the last restore drill.
 * Running a backup and downloading one need `backups.manage`.
 */
class SystemHealth extends Page implements HasTable
{
    use InteractsWithTable;

    public const BACKUP_REQUESTED = 'portal:backup-requested';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHeart;

    protected static ?string $slug = 'health';

    protected static ?int $navigationSort = 95;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.nav.administration');
    }

    public static function getNavigationLabel(): string
    {
        return __('health.title');
    }

    public function getTitle(): string
    {
        return __('health.title');
    }

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->can('settings.view');
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('runChecks')
                ->label(__('health.action.run'))
                ->icon(Heroicon::OutlinedArrowPath)
                ->action(function (): void {
                    abort_unless(CurrentUser::get()->can('settings.view'), 403);
                    Artisan::call(RunHealthChecksCommand::class, ['--no-notification' => true]);
                    Notification::make()->success()->title(__('health.action.ran'))->send();
                }),
            Action::make('testMail')
                ->label(__('health.action.test_mail'))
                ->icon(Heroicon::OutlinedEnvelope)
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription(fn (): string => __('health.action.test_mail_help', ['email' => CurrentUser::get()->email]))
                ->action(function (): void {
                    $user = CurrentUser::get();
                    abort_unless($user->can('settings.view'), 403);
                    try {
                        Mail::to($user->email)->locale($user->locale ?? 'en')->send(new TestMail($user->name));
                        Notification::make()->success()->title(__('health.action.test_mail_sent', ['email' => $user->email]))->send();
                    } catch (Throwable $e) {
                        report($e);
                        Notification::make()->danger()->title(__('health.action.test_mail_failed'))->body(Str::limit($e->getMessage(), 300))->persistent()->send();
                    }
                }),
        ];
    }

    public function content(Schema $schema): Schema
    {
        $results = app(ResultStore::class)->latestResults();

        return $schema->components([
            Section::make(__('health.section.checks'))
                ->description($this->checkedAt($results))
                ->icon(Heroicon::OutlinedHeart)
                ->schema($results === null || $results->storedCheckResults->isEmpty()
                    ? [TextEntry::make('none')->hiddenLabel()->state(__('health.none'))]
                    : [Grid::make(['default' => 1, 'md' => 2, 'xl' => 3])->schema($this->entries($results))]),
            Section::make(__('health.drill.title'))
                ->icon(Heroicon::OutlinedLifebuoy)
                ->description(__('health.drill.help'))
                ->schema($this->drill()),
            EmbeddedTable::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => collect(BackupArchive::all())->keyBy('path')->all())
            ->heading(__('health.backups.title'))
            ->description(__('health.backups.help'))
            ->paginated(false)
            ->headerActions([
                Action::make('backupNow')
                    ->label(__('health.backups.now'))
                    ->icon(Heroicon::OutlinedArchiveBoxArrowDown)
                    ->visible(fn (): bool => CurrentUser::get()->can('backups.manage'))
                    ->requiresConfirmation()
                    ->modalDescription(__('health.backups.now_help'))
                    ->action(function (): void {
                        abort_unless(CurrentUser::get()->can('backups.manage'), 403);
                        Cache::put(self::BACKUP_REQUESTED, true, now()->addHour());
                        Notification::make()->success()->title(__('health.backups.requested'))->send();
                    }),
            ])
            ->columns([
                TextColumn::make('date')->label(__('health.backups.date'))
                    ->formatStateUsing(CalendarDate::format(withTime: true))
                    ->description(fn (array $record): string => $record['date'] instanceof CarbonImmutable ? $record['date']->diffForHumans() : ''),
                TextColumn::make('name')->label(__('health.backups.file'))->fontFamily('mono')->size('xs'),
                TextColumn::make('size')->label(__('health.backups.size'))->formatStateUsing(fn (int $state): string => Number::fileSize($state, precision: 1)),
            ])
            ->recordActions([
                Action::make('download')
                    ->label(__('health.backups.download'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('gray')
                    ->visible(fn (): bool => CurrentUser::get()->can('backups.manage'))
                    ->action(function (array $record): StreamedResponse {
                        abort_unless(CurrentUser::get()->can('backups.manage') && BackupArchive::exists((string) $record['path']), 403);
                        activity('backups')->causedBy(CurrentUser::get())->event('download')
                            ->withProperties(['backup' => $record['path']])->log('Backup downloaded');

                        return Storage::disk(BackupArchive::DISK)->download((string) $record['path']);
                    }),
            ])
            ->emptyStateHeading(__('health.backups.empty'))
            ->emptyStateIcon(Heroicon::OutlinedArchiveBox);
    }

    private function checkedAt(?StoredCheckResults $results): ?string
    {
        if ($results === null || $results->storedCheckResults->isEmpty()) {
            return null;
        }

        return __('health.checked_at', ['time' => (string) CalendarDate::format(withTime: true)($results->finishedAt)]);
    }

    /**
     * @return list<TextEntry>
     */
    private function entries(StoredCheckResults $results): array
    {
        $order = array_flip(HealthChecks::NAMES);

        return $results->storedCheckResults
            ->sortBy(fn (StoredCheckResult $result): int => $order[$result->name] ?? 99)
            ->map(fn (StoredCheckResult $result): TextEntry => TextEntry::make("check_{$result->name}")
                ->label(HealthChecks::label($result->name))
                ->state(trim(HealthChecks::statusLabel($result->status).($result->shortSummary !== '' && ! in_array(Str::lower($result->shortSummary), ['ok', 'failed', 'warning'], true) ? ' · '.$result->shortSummary : '')))
                ->badge()
                ->color(HealthChecks::color($result->status))
                ->belowContent(filled($result->notificationMessage) ? Str::limit((string) $result->notificationMessage, 300) : null))
            ->values()
            ->all();
    }

    /**
     * @return list<TextEntry>
     */
    private function drill(): array
    {
        $last = RestoreDrill::history()[0] ?? null;
        if ($last === null) {
            return [TextEntry::make('drill_none')->hiddenLabel()->state(__('health.drill.none'))];
        }

        return [
            TextEntry::make('drill_result')->label(__('health.drill.result'))
                ->state(($last['ok'] ?? false) ? __('health.drill.passed') : __('health.drill.failed'))
                ->badge()->color(($last['ok'] ?? false) ? 'success' : 'danger')
                ->belowContent(is_string($last['error'] ?? null) ? Str::limit($last['error'], 300) : null),
            TextEntry::make('drill_at')->label(__('health.drill.at'))
                ->state((string) CalendarDate::format(withTime: true)((string) ($last['at'] ?? ''))),
            TextEntry::make('drill_backup')->label(__('health.backups.file'))->state(basename((string) ($last['backup'] ?? '')))->fontFamily('mono'),
            TextEntry::make('drill_tables')->label(__('health.drill.tables'))
                ->state(__('health.drill.summary', ['tables' => (int) ($last['tables'] ?? 0), 'migrations' => (int) ($last['migrations'] ?? 0), 'seconds' => (int) ($last['seconds'] ?? 0)])),
        ];
    }
}
