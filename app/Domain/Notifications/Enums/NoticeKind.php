<?php

namespace App\Domain\Notifications\Enums;

use App\Domain\Notifications\Support\NoticePreferences;
use App\Models\User;

/**
 * The kinds of notice staff receive (PLAN.md §7, ADR-0036). Each person
 * chooses in-app, email, both or neither per kind; a kind is only offered to
 * people whose work it concerns.
 */
enum NoticeKind: string
{
    case Workflow = 'workflow';
    case Tickets = 'tickets';
    case Registrations = 'registrations';
    case Applications = 'applications';
    case Volunteers = 'volunteers';
    case MemberRequests = 'member_requests';
    case Imports = 'imports';
    case Sources = 'sources';
    case Health = 'health';
    case Security = 'security';

    public function label(): string
    {
        return match ($this) {
            self::Workflow => __('notifications.kind.workflow'),
            self::Tickets => __('notifications.kind.tickets'),
            self::Registrations => __('notifications.kind.registrations'),
            self::Applications => __('notifications.kind.applications'),
            self::Volunteers => __('notifications.kind.volunteers'),
            self::MemberRequests => __('notifications.kind.member_requests'),
            self::Imports => __('notifications.kind.imports'),
            self::Sources => __('notifications.kind.sources'),
            self::Health => __('notifications.kind.health'),
            self::Security => __('notifications.kind.security'),
        };
    }

    public function help(): string
    {
        return match ($this) {
            self::Workflow => __('notifications.kind.workflow_help'),
            self::Tickets => __('notifications.kind.tickets_help'),
            self::Registrations => __('notifications.kind.registrations_help'),
            self::Applications => __('notifications.kind.applications_help'),
            self::Volunteers => __('notifications.kind.volunteers_help'),
            self::MemberRequests => __('notifications.kind.member_requests_help'),
            self::Imports => __('notifications.kind.imports_help'),
            self::Sources => __('notifications.kind.sources_help'),
            self::Health => __('notifications.kind.health_help'),
            self::Security => __('notifications.kind.security_help'),
        };
    }

    /**
     * Registrations can be many and imports are routine: in-app only unless
     * the person asks for email too.
     *
     * @return list<string>
     */
    public function defaults(): array
    {
        return match ($this) {
            self::Registrations, self::Imports => [NoticePreferences::IN_APP],
            default => [NoticePreferences::IN_APP, NoticePreferences::EMAIL],
        };
    }

    public function concerns(User $user): bool
    {
        return match ($this) {
            self::Workflow => $user->can('posts.create') || $user->can('posts.update_own') || $user->can('posts.review') || $user->can('posts.publish'),
            self::Tickets => $user->can('inbox.view'),
            self::Registrations => $user->can('events.registrations'),
            self::Applications => $user->can('applications.view'),
            self::Volunteers => $user->can('volunteers.view'),
            self::MemberRequests => $user->can('members.update'),
            self::Imports => $user->can('posts.review') || $user->can('translations.view'),
            self::Sources => $user->can('social.manage') || $user->can('settings.view'),
            self::Health => $user->can('settings.view'),
            // Security alerts always arrive in the portal and by email: there is no choice to offer.
            self::Security => false,
        };
    }

    /**
     * @return list<self>
     */
    public static function for(User $user): array
    {
        return array_values(array_filter(self::cases(), fn (self $kind): bool => $kind->concerns($user)));
    }
}
