<?php

namespace App\Filament\Resources\Subscribers\Pages;

use App\Domain\Engagement\Models\Subscriber;
use App\Domain\Engagement\Support\SubscriptionOptions;
use App\Domain\Events\Support\RegistrationExport;
use App\Filament\Resources\Subscribers\SubscriberResource;
use App\Filament\Support\CurrentUser;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListSubscribers extends ListRecords
{
    protected static string $resource = SubscriberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label(__('membership.admin.export'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->visible(fn (): bool => CurrentUser::get()->can('subscribers.export'))
                ->action(fn (): StreamedResponse => $this->export()),
        ];
    }

    private function export(): StreamedResponse
    {
        $actor = CurrentUser::get();
        abort_unless($actor->can('subscribers.export'), 403);
        /** @var Builder<Subscriber> $query */
        $query = $this->getFilteredTableQuery();
        activity('newsletter')->causedBy($actor)->event('exported')->withProperties(['count' => (clone $query)->count()])->log('Subscribers exported');

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'wb');
            fwrite($out, "\u{FEFF}");
            fputcsv($out, array_map(fn (string $key): string => __("newsletter.export.{$key}"), ['email', 'phone', 'status', 'channels', 'topics', 'language', 'signed_up']), escape: '');
            $query->reorder()->orderBy('created_at')->chunk(500, function (Collection $subscribers) use ($out): void {
                /** @var Subscriber $subscriber */
                foreach ($subscribers as $subscriber) {
                    fputcsv($out, array_map(RegistrationExport::cell(...), [
                        (string) $subscriber->email,
                        (string) $subscriber->phone,
                        __('newsletter.state.'.SubscriberResource::state($subscriber)),
                        implode(', ', array_map(fn (string $c): string => SubscriptionOptions::channels()[$c] ?? $c, $subscriber->channels)),
                        implode(', ', array_map(fn (string $t): string => SubscriptionOptions::topics()[$t] ?? $t, $subscriber->topics)),
                        $subscriber->locale,
                        $subscriber->created_at->toDateString(),
                    ]), escape: '');
                }
            });
            fclose($out);
        }, 'subscribers-'.CarbonImmutable::now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
