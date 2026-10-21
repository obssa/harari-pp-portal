<?php

namespace App\Filament\Resources\MembershipApplications\Pages;

use App\Domain\Membership\Models\MembershipApplication;
use App\Domain\Membership\Support\ApplicationExport;
use App\Filament\Resources\MembershipApplications\MembershipApplicationResource;
use App\Filament\Support\CurrentUser;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListMembershipApplications extends ListRecords
{
    protected static string $resource = MembershipApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label(__('membership.admin.export'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->visible(fn (): bool => CurrentUser::get()->can('applications.export'))
                ->action(fn (): StreamedResponse => $this->export()),
        ];
    }

    private function export(): StreamedResponse
    {
        $actor = CurrentUser::get();
        abort_unless($actor->can('applications.export'), 403);
        // What the screen shows is what is exported: the officer's woredas and the chosen filters.
        /** @var Builder<MembershipApplication> $query */
        $query = $this->getFilteredTableQuery();
        $count = (clone $query)->count();

        activity('membership')->causedBy($actor)->event('exported')
            ->withProperties(['count' => $count])->log('Membership applications exported');

        return response()->streamDownload(
            fn () => ApplicationExport::write($query, fopen('php://output', 'wb')),
            'membership-applications-'.CarbonImmutable::now()->format('Y-m-d').'.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }
}
