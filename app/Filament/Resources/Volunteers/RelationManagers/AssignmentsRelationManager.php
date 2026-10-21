<?php

namespace App\Filament\Resources\Volunteers\RelationManagers;

use App\Domain\Access\Support\OfficeScope;
use App\Domain\Events\Models\Event;
use App\Domain\Volunteers\Actions\ManageVolunteer;
use App\Domain\Volunteers\Enums\AssignmentStatus;
use App\Domain\Volunteers\Models\Volunteer;
use App\Domain\Volunteers\Models\VolunteerAssignment;
use App\Filament\Support\CalendarDate;
use App\Filament\Support\CurrentUser;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Component;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * A volunteer's tasks, at events or on their own, and the hours given
 * (ADR-0048). Planned and updated by those who manage volunteers.
 */
class AssignmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'assignments';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('volunteers.assignment.title');
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        if (! $ownerRecord instanceof Volunteer) {
            return null;
        }
        $hours = ManageVolunteer::hours($ownerRecord);

        return $hours > 0 ? __('volunteers.assignment.hours_badge', ['hours' => rtrim(rtrim(number_format($hours, 2, '.', ''), '0'), '.')]) : null;
    }

    /** Tasks are planned from the read-only volunteer page. */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('event.translations'))
            ->columns([
                TextColumn::make('task')->label(__('volunteers.assignment.task'))->wrap(),
                TextColumn::make('event_title')->label(__('volunteers.assignment.event'))
                    ->state(fn (VolunteerAssignment $record): ?string => $record->event?->title())->placeholder('—'),
                TextColumn::make('on_date')->label(__('volunteers.assignment.on_date'))->formatStateUsing(CalendarDate::format())->placeholder('—'),
                TextColumn::make('status')->label(__('membership.admin.status'))->badge()
                    ->formatStateUsing(fn (AssignmentStatus $state): string => $state->label())->color(fn (AssignmentStatus $state): string => $state->color()),
                TextColumn::make('hours')->label(__('volunteers.assignment.hours'))->placeholder('—'),
            ])
            ->headerActions([
                Action::make('assign')
                    ->label(__('volunteers.assignment.add'))
                    ->icon(Heroicon::OutlinedPlus)
                    ->visible(fn (): bool => $this->canManage())
                    ->schema($this->fields())
                    ->action(function (array $data): void {
                        app(ManageVolunteer::class)->saveAssignment($this->volunteer(), null, $data, CurrentUser::get());
                        Notification::make()->success()->title(__('volunteers.assignment.saved'))->send();
                    }),
            ])
            ->recordActions([
                Action::make('edit')
                    ->label(__('volunteers.assignment.edit'))
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->visible(fn (): bool => $this->canManage())
                    ->fillForm(fn (VolunteerAssignment $record): array => [
                        'event_id' => $record->event_id, 'task' => $record->task, 'on_date' => $record->on_date?->toDateString(),
                        'status' => $record->status->value, 'hours' => $record->hours,
                    ])
                    ->schema($this->fields())
                    ->action(function (VolunteerAssignment $record, array $data): void {
                        app(ManageVolunteer::class)->saveAssignment($this->volunteer(), $record, $data, CurrentUser::get());
                        Notification::make()->success()->title(__('volunteers.assignment.saved'))->send();
                    }),
            ])
            ->emptyStateHeading(__('volunteers.assignment.empty'));
    }

    /**
     * @return list<Component>
     */
    private function fields(): array
    {
        return [
            Select::make('event_id')->label(__('volunteers.assignment.event'))->searchable()
                ->helperText(__('volunteers.assignment.event_help'))
                ->options(fn (): array => Event::query()->where('starts_at', '>=', CarbonImmutable::now()->subMonths(3))
                    ->with('translations')->orderBy('starts_at')->limit(200)->get()
                    ->mapWithKeys(fn (Event $event): array => [$event->id => $event->starts_at->toDateString().' · '.$event->title()])->all()),
            TextInput::make('task')->label(__('volunteers.assignment.task'))->maxLength(120)->helperText(__('volunteers.assignment.task_help')),
            DatePicker::make('on_date')->label(__('volunteers.assignment.on_date')),
            Select::make('status')->label(__('membership.admin.status'))->options(AssignmentStatus::options())->default(AssignmentStatus::Planned->value)->required(),
            TextInput::make('hours')->label(__('volunteers.assignment.hours'))->numeric()->minValue(0)->maxValue(999)->step(0.25),
        ];
    }

    private function canManage(): bool
    {
        return OfficeScope::covers(CurrentUser::get(), $this->volunteer()->woreda_id, 'volunteers.manage');
    }

    private function volunteer(): Volunteer
    {
        /** @var Volunteer $volunteer */
        $volunteer = $this->getOwnerRecord();

        return $volunteer;
    }
}
