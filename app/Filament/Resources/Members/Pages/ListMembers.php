<?php

namespace App\Filament\Resources\Members\Pages;

use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Support\MemberExport;
use App\Filament\Resources\Members\MemberResource;
use App\Filament\Support\CurrentUser;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListMembers extends ListRecords
{
    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('export')
                ->label(__('membership.admin.export'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->visible(fn (): bool => CurrentUser::get()->can('members.export'))
                ->action(fn (): StreamedResponse => $this->export()),
        ];
    }

    private function export(): StreamedResponse
    {
        $actor = CurrentUser::get();
        abort_unless($actor->can('members.export'), 403);
        // What the screen shows is what is exported: the officer's woredas, the search and the filters.
        /** @var Builder<Member> $query */
        $query = $this->getFilteredTableQuery();
        $count = (clone $query)->count();

        activity('membership')->causedBy($actor)->event('exported')
            ->withProperties(['count' => $count, 'of' => 'members'])->log('Members exported');

        return response()->streamDownload(
            fn () => MemberExport::write($query, fopen('php://output', 'wb')),
            'members-'.CarbonImmutable::now()->format('Y-m-d').'.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }
}
