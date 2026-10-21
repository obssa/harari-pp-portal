<?php

namespace App\Domain\Volunteers\Notifications;

use App\Domain\Notifications\Enums\NoticeKind;
use App\Domain\Notifications\Notifications\StaffNotice;
use App\Domain\Volunteers\Models\Volunteer;
use App\Filament\Resources\Volunteers\VolunteerResource;

/**
 * Someone offered to volunteer in a woreda the coordinator handles
 * (ADR-0048). Only the woreda: the person's details stay in the admin.
 * Unread notices for one woreda are combined.
 */
final class NewVolunteerNotice extends StaffNotice
{
    /**
     * @param  array<string, string>  $woredas  the woreda's name per language, empty without a woreda
     */
    public function __construct(
        public readonly string $volunteerId,
        public readonly ?string $woredaId,
        public readonly array $woredas,
    ) {}

    public static function for(Volunteer $volunteer): self
    {
        $woreda = $volunteer->woreda;

        return new self(
            $volunteer->id,
            $volunteer->woreda_id,
            $woreda === null ? [] : self::perLanguage(fn (string $locale): string => $woreda->name($locale)),
        );
    }

    public function kind(): NoticeKind
    {
        return NoticeKind::Volunteers;
    }

    public function group(): string
    {
        return 'volunteers:'.($this->woredaId ?? 'none');
    }

    protected function title(string $locale): string
    {
        return __('volunteers.notice.title', [], $locale);
    }

    protected function lines(string $locale): array
    {
        return [$this->woredas === []
            ? __('volunteers.notice.no_woreda', [], $locale)
            : __('membership.notice.woreda', ['woreda' => self::pick($this->woredas, $locale)], $locale)];
    }

    protected function url(): string
    {
        return VolunteerResource::getUrl('view', ['record' => $this->volunteerId], panel: 'admin');
    }

    protected function actionLabel(string $locale): string
    {
        return __('volunteers.notice.open', [], $locale);
    }

    protected function icon(): string
    {
        return 'heroicon-o-hand-raised';
    }
}
