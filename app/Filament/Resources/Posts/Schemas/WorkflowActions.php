<?php

namespace App\Filament\Resources\Posts\Schemas;

use App\Domain\Content\Models\Post;
use App\Domain\Content\Workflow\ContentWorkflow;
use App\Domain\Content\Workflow\Transition;
use App\Filament\Support\CurrentUser;
use App\Services\Calendar\EthiopianCalendar;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Workflow buttons inside each language tab of a post (submit, send back,
 * mark reviewed, publish, schedule, unpublish, archive, reopen). Only the
 * moves the signed-in person may make from the current status are shown.
 */
final class WorkflowActions
{
    public static function make(string $locale): Actions
    {
        return Actions::make(array_map(
            fn (Transition $transition): Action => self::action($locale, $transition),
            Transition::cases(),
        ))
            ->visible(fn (?Post $record): bool => $record !== null)
            ->key("workflow-{$locale}");
    }

    private static function action(string $locale, Transition $transition): Action
    {
        $action = Action::make("{$transition->value}_{$locale}")
            ->label($transition->label())
            ->color(match ($transition) {
                Transition::Publish => 'success',
                Transition::SendBack, Transition::Unpublish, Transition::Archive => 'danger',
                default => 'gray',
            })
            ->size('sm')
            ->visible(fn (?Post $record): bool => $record !== null && in_array(
                $transition,
                app(ContentWorkflow::class)->available($record, $locale, CurrentUser::get()),
                true,
            ))
            ->action(function (array $data, Post $record, Component $livewire) use ($locale, $transition): void {
                try {
                    app(ContentWorkflow::class)->apply(
                        $record,
                        $locale,
                        $transition,
                        CurrentUser::get(),
                        $data['comment'] ?? null,
                        isset($data['at']) ? CarbonImmutable::parse((string) $data['at']) : null,
                    );
                } catch (ValidationException $e) {
                    Notification::make()->danger()->title(collect($e->errors())->flatten()->first())->send();

                    return;
                }

                Notification::make()->success()->title(__('content.workflow.done', ['transition' => $transition->label()]))->send();
                $livewire->redirect(request()->header('Referer') ?? url()->current());
            });

        return match ($transition) {
            Transition::SendBack => $action->schema([
                Textarea::make('comment')->label(__('content.workflow.comment'))->required()->rows(3)->maxLength(2000),
            ]),
            Transition::Submit, Transition::MarkReviewed => $action->schema([
                Textarea::make('comment')->label(__('content.workflow.comment_optional'))->rows(2)->maxLength(2000),
            ]),
            Transition::Schedule => $action->schema([
                DateTimePicker::make('at')
                    ->label(__('content.workflow.publish_at'))
                    ->timezone(EthiopianCalendar::timezone())
                    ->seconds(false)
                    ->minDate(now())
                    ->required(),
            ]),
            default => $action->requiresConfirmation(),
        };
    }
}
