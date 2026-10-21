<?php

namespace App\Domain\Membership\Notifications;

use App\Domain\Membership\Models\MembershipApplication;
use App\Domain\Notifications\Enums\NoticeKind;
use App\Domain\Notifications\Notifications\StaffNotice;
use App\Filament\Resources\MembershipApplications\MembershipApplicationResource;

/**
 * A membership application arrived for a woreda the officer handles
 * (ADR-0045). Only the reference and the woreda: the applicant's details
 * stay in the review queue. Unread notices for one woreda are combined.
 */
final class NewApplicationNotice extends StaffNotice
{
    /**
     * @param  array<string, string>  $woredas  the woreda's name per language, empty without a woreda
     */
    public function __construct(
        public readonly string $applicationId,
        public readonly string $reference,
        public readonly ?string $woredaId,
        public readonly array $woredas,
    ) {}

    public static function for(MembershipApplication $application): self
    {
        $woreda = $application->woreda;

        return new self(
            $application->id,
            $application->reference,
            $application->woreda_id,
            $woreda === null ? [] : self::perLanguage(fn (string $locale): string => $woreda->name($locale)),
        );
    }

    public function kind(): NoticeKind
    {
        return NoticeKind::Applications;
    }

    public function group(): string
    {
        return 'applications:'.($this->woredaId ?? 'none');
    }

    protected function title(string $locale): string
    {
        return __('membership.notice.title', ['reference' => $this->reference], $locale);
    }

    protected function lines(string $locale): array
    {
        return [$this->woredas === []
            ? __('membership.notice.no_woreda', [], $locale)
            : __('membership.notice.woreda', ['woreda' => self::pick($this->woredas, $locale)], $locale)];
    }

    protected function url(): string
    {
        return MembershipApplicationResource::getUrl('view', ['record' => $this->applicationId], panel: 'admin');
    }

    protected function actionLabel(string $locale): string
    {
        return __('membership.notice.open', [], $locale);
    }

    protected function icon(): string
    {
        return 'heroicon-o-user-plus';
    }
}
