<?php

namespace App\Filament\Resources\MemberImports\Pages;

use App\Domain\Membership\Actions\ImportMembers;
use App\Domain\Membership\Models\MemberImport;
use App\Filament\Resources\MemberImports\MemberImportResource;
use App\Filament\Support\CurrentUser;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ViewMemberImport extends ViewRecord
{
    protected static string $resource = MemberImportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('apply')
                ->label(fn (): string => __('members.import.apply', ['count' => $this->import()->ready]))
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->visible(fn (): bool => $this->import()->status === MemberImport::CHECKED && $this->import()->ready > 0)
                ->requiresConfirmation()
                ->modalDescription(fn (): string => __('members.import.apply_help', ['count' => $this->import()->ready, 'skipped' => $this->import()->with_problems + $this->import()->duplicates]))
                ->action(function (): void {
                    app(ImportMembers::class)->apply($this->import(), CurrentUser::get());
                    Notification::make()->success()->title(__('members.import.applying'))->send();
                    $this->refreshFormData(['status']);
                }),
            Action::make('discard')
                ->label(__('members.import.discard'))
                ->icon(Heroicon::OutlinedXMark)
                ->color('gray')
                ->visible(fn (): bool => $this->import()->status === MemberImport::CHECKED)
                ->requiresConfirmation()
                ->action(function (): void {
                    app(ImportMembers::class)->discard($this->import(), CurrentUser::get());
                    Notification::make()->success()->title(__('members.import.discarded'))->send();
                    $this->refreshFormData(['status']);
                }),
            Action::make('rollback')
                ->label(__('members.import.rollback'))
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->color('danger')
                ->visible(fn (): bool => $this->import()->canRollBack() || $this->import()->status === MemberImport::FAILED)
                ->requiresConfirmation()
                ->modalDescription(fn (): string => __('members.import.rollback_help', ['count' => $this->import()->members()->count()]))
                ->action(function (): void {
                    $removed = app(ImportMembers::class)->rollback($this->import(), CurrentUser::get());
                    Notification::make()->success()->title(__('members.import.rolled_back', ['count' => $removed]))->send();
                    $this->refreshFormData(['status', 'rolled_back_at']);
                }),
            Action::make('problems')
                ->label(__('members.import.download_problems'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->visible(fn (): bool => $this->import()->problems !== [])
                ->action(fn (): StreamedResponse => response()->streamDownload(function (): void {
                    $out = fopen('php://output', 'wb');
                    fwrite($out, "\u{FEFF}");
                    foreach (MemberImportResource::problemLines($this->import()) as $line) {
                        fputcsv($out, [$line], escape: '');
                    }
                    fclose($out);
                }, 'import-problems-'.$this->import()->id.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8'])),
        ];
    }

    private function import(): MemberImport
    {
        /** @var MemberImport $import */
        $import = $this->getRecord();

        return $import;
    }
}
