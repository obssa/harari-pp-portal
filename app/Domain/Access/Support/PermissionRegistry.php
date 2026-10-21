<?php

namespace App\Domain\Access\Support;

/**
 * The single list of permissions in the portal, named `module.action`
 * (PLAN.md §8). Modules for later phases are listed now so the default
 * roles are complete from day one; their screens arrive with their phase.
 */
final class PermissionRegistry
{
    /** @var array<string, list<string>> */
    private const MODULES = [
        'admin' => ['access'],
        'dashboard' => ['view'],
        'users' => ['view', 'create', 'update', 'delete', 'assign_roles', 'reset_mfa', 'impersonate'],
        'roles' => ['view', 'create', 'update', 'delete'],
        'activity' => ['view', 'export'],
        'settings' => ['view', 'manage'],
        'integrations' => ['manage'],
        'backups' => ['manage'],
        'audit' => ['purge'],
        'posts' => ['view', 'create', 'update', 'update_own', 'translate', 'review', 'publish', 'feature', 'delete'],
        'pages' => ['view', 'create', 'update', 'translate', 'publish', 'delete'],
        'taxonomies' => ['manage'],
        'redirects' => ['manage'],
        'homepage' => ['manage'],
        'menus' => ['manage'],
        'media' => ['view', 'upload', 'update', 'delete'],
        'galleries' => ['manage'],
        'videos' => ['manage'],
        'leaders' => ['view', 'manage'],
        'offices' => ['view', 'manage'],
        'leagues' => ['view', 'manage'],
        'events' => ['view', 'manage', 'registrations'],
        'documents' => ['view', 'manage'],
        'projects' => ['view', 'manage'],
        'forms' => ['manage'],
        'inbox' => ['view', 'reply', 'assign'],
        'translations' => ['view', 'export', 'import', 'glossary'],
        'social' => ['view', 'manage', 'share'],
        'press_kit' => ['manage'],
        'media_contacts' => ['manage'],
        'campaigns' => ['view', 'manage', 'send'],
        'subscribers' => ['view', 'manage', 'export'],
        'members' => ['view', 'create', 'update', 'delete', 'import', 'export'],
        'applications' => ['view', 'review', 'export'],
        'volunteers' => ['view', 'manage'],
        'analytics' => ['view', 'export'],
        'privacy' => ['manage'],
        'portal' => ['access'],
    ];

    /**
     * @return array<string, list<string>> module => permission names
     */
    public static function modules(): array
    {
        $modules = [];
        foreach (self::MODULES as $module => $actions) {
            $modules[$module] = array_map(fn (string $action): string => "{$module}.{$action}", $actions);
        }

        // UI-string editing is granted per language (PLAN.md §8: "translations.har.edit").
        foreach ((array) config('portal.locales') as $locale) {
            $modules['translations'][] = "translations.{$locale}.edit";
        }

        return $modules;
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_merge(...array_values(self::modules()));
    }

    public static function exists(string $permission): bool
    {
        return in_array($permission, self::all(), true);
    }

    /**
     * Human label for a permission, e.g. "Publish" for posts.publish or
     * "Edit Harari" for translations.har.edit.
     */
    public static function label(string $permission): string
    {
        $parts = explode('.', $permission);

        if (count($parts) === 3 && $parts[0] === 'translations' && $parts[2] === 'edit') {
            return __('access.action.edit_locale', ['language' => __('access.language.'.$parts[1])]);
        }

        return __('access.action.'.$parts[1]);
    }

    public static function moduleLabel(string $module): string
    {
        return __('access.module.'.$module);
    }
}
