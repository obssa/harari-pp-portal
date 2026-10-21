<?php

namespace App\Domain\Access\Enums;

use App\Domain\Access\Support\PermissionRegistry;

/**
 * The roles PLAN.md §8 defines. Their default permissions are seeded by
 * `portal:sync-permissions`; Super Admins may change grants afterwards
 * (except for the Super Admin role, which always has everything).
 */
enum SystemRole: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Publisher = 'publisher';
    case Editor = 'editor';
    case Author = 'author';
    case Translator = 'translator';
    case MediaManager = 'media_manager';
    case CommunicationsOfficer = 'communications_officer';
    case MembershipOfficer = 'membership_officer';
    case SupportAgent = 'support_agent';
    case Analyst = 'analyst';
    case OfficeHead = 'office_head';
    case Member = 'member';

    /** Permissions only a Super Admin holds (PLAN.md §8: Admin "cannot"). */
    public const SUPER_ADMIN_ONLY = [
        'settings.manage',
        'integrations.manage',
        'backups.manage',
        'audit.purge',
        'users.impersonate',
    ];

    public function label(): string
    {
        return __('access.role.'.$this->value);
    }

    public function requiresMfa(): bool
    {
        return in_array($this->value, (array) config('portal.mfa_required_roles'), true);
    }

    /**
     * @return list<ScopeType>
     */
    public function allowedScopes(): array
    {
        return match ($this) {
            self::Publisher, self::Editor, self::Author => [ScopeType::Office, ScopeType::League],
            self::Translator => [ScopeType::Language],
            self::MembershipOfficer, self::SupportAgent, self::OfficeHead => [ScopeType::Office],
            default => [],
        };
    }

    /**
     * @return list<string>
     */
    public function defaultPermissions(): array
    {
        $staff = ['admin.access', 'dashboard.view'];

        $permissions = match ($this) {
            self::SuperAdmin => PermissionRegistry::all(),

            self::Admin => array_values(array_diff(
                PermissionRegistry::all(),
                self::SUPER_ADMIN_ONLY,
                ['portal.access'],
            )),

            self::Publisher => [
                ...$staff,
                'posts.view', 'posts.create', 'posts.update', 'posts.translate', 'posts.review',
                'posts.publish', 'posts.feature', 'posts.delete', 'social.share',
                'pages.view', 'pages.create', 'pages.update', 'pages.translate', 'pages.publish', 'pages.delete',
                'homepage.manage', 'menus.manage', 'taxonomies.manage', 'redirects.manage', 'forms.manage',
                'inbox.view', 'inbox.reply', 'inbox.assign',
                'media.view', 'media.upload', 'media.update',
                'galleries.manage', 'videos.manage',
                'leaders.view', 'leaders.manage', 'offices.view', 'offices.manage',
                'leagues.view', 'leagues.manage', 'events.view', 'events.manage', 'events.registrations',
                'documents.view', 'documents.manage', 'projects.view', 'projects.manage',
                'translations.view', 'analytics.view',
            ],

            self::Editor => [
                ...$staff,
                'posts.view', 'posts.create', 'posts.update', 'posts.translate', 'posts.review',
                'pages.view', 'pages.create', 'pages.update', 'pages.translate', 'taxonomies.manage',
                'media.view', 'media.upload', 'media.update',
                'galleries.manage', 'videos.manage',
                'leaders.view', 'leaders.manage', 'offices.view', 'offices.manage',
                'leagues.view', 'leagues.manage', 'events.view', 'events.manage',
                'documents.view', 'documents.manage', 'projects.view', 'projects.manage',
                'translations.view',
            ],

            self::Author => [
                ...$staff,
                'posts.view', 'posts.create', 'posts.update_own',
                'media.view', 'media.upload',
            ],

            self::Translator => [
                ...$staff,
                'posts.view', 'posts.translate', 'pages.view', 'pages.translate',
                'translations.view', 'translations.export', 'translations.import', 'translations.glossary',
                ...array_map(
                    fn (string $locale): string => "translations.{$locale}.edit",
                    (array) config('portal.locales'),
                ),
            ],

            self::MediaManager => [
                ...$staff,
                'media.view', 'media.upload', 'media.update', 'media.delete',
                'galleries.manage', 'videos.manage', 'social.view', 'social.manage',
            ],

            self::CommunicationsOfficer => [
                ...$staff,
                'posts.view', 'posts.create', 'posts.update', 'social.share',
                'press_kit.manage', 'media_contacts.manage', 'forms.manage', 'inbox.view', 'inbox.reply',
                'campaigns.view', 'campaigns.manage', 'campaigns.send',
                'subscribers.view', 'subscribers.manage',
                'media.view', 'media.upload', 'analytics.view',
            ],

            self::MembershipOfficer => [
                ...$staff,
                'applications.view', 'applications.review', 'applications.export',
                'members.view', 'members.create', 'members.update', 'members.import', 'members.export',
                'volunteers.view', 'volunteers.manage', 'campaigns.send', 'events.view', 'events.registrations',
            ],

            self::SupportAgent => [...$staff, 'inbox.view', 'inbox.reply', 'inbox.assign'],

            self::Analyst => [...$staff, 'analytics.view', 'analytics.export'],

            self::OfficeHead => [...$staff, 'posts.view', 'events.view', 'documents.view', 'inbox.view'],

            self::Member => ['portal.access'],
        };

        return array_values(array_unique($permissions));
    }
}
