<?php

namespace App\Domain\Membership\Notifications;

use App\Domain\Membership\Models\Member;
use App\Domain\Notifications\Enums\NoticeKind;
use App\Domain\Notifications\Notifications\StaffNotice;
use App\Filament\Resources\Members\MemberResource;

/**
 * A member asked from the portal for a correction (ADR-0049). Only the member
 * number: what was asked is in the member's history.
 */
final class MemberChangeRequestNotice extends StaffNotice
{
    public function __construct(
        public readonly string $memberId,
        public readonly string $memberNo,
    ) {}

    public static function for(Member $member): self
    {
        return new self($member->id, $member->member_no);
    }

    public function kind(): NoticeKind
    {
        return NoticeKind::MemberRequests;
    }

    public function group(): string
    {
        return 'member-request:'.$this->memberId;
    }

    protected function title(string $locale): string
    {
        return __('members.portal.notice_title', ['number' => $this->memberNo], $locale);
    }

    protected function lines(string $locale): array
    {
        return [__('members.portal.notice_text', [], $locale)];
    }

    protected function url(): string
    {
        return MemberResource::getUrl('view', ['record' => $this->memberId], panel: 'admin');
    }

    protected function actionLabel(string $locale): string
    {
        return __('members.portal.notice_open', [], $locale);
    }

    protected function icon(): string
    {
        return 'heroicon-o-pencil-square';
    }
}
