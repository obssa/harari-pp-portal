<?php

namespace App\Domain\Translation\Support;

/**
 * The portal's languages and how a visitor's language is chosen
 * (PLAN.md §3: URL, then cookie, then Accept-Language, then default).
 */
final class Locales
{
    /** Remembers the visitor's language choice for one year (unencrypted, not sensitive). */
    public const COOKIE = 'hpp_locale';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_values(array_map('strval', (array) config('portal.locales')));
    }

    /** @var (\Closure(): ?string)|null */
    private static ?\Closure $defaultResolver = null;

    /**
     * Lets the admin setting (SiteSettings::default_locale) decide the default;
     * config('portal.default_locale') is used when the setting is unavailable.
     *
     * @param  (\Closure(): ?string)|null  $resolver
     */
    public static function resolveDefaultUsing(?\Closure $resolver): void
    {
        self::$defaultResolver = $resolver;
    }

    public static function default(): string
    {
        $candidates = [
            self::$defaultResolver !== null ? (self::$defaultResolver)() : null,
            (string) config('portal.default_locale'),
        ];

        foreach ($candidates as $candidate) {
            if (self::isSupported($candidate)) {
                return (string) $candidate;
            }
        }

        return self::all()[0];
    }

    public static function isSupported(?string $locale): bool
    {
        return $locale !== null && in_array($locale, self::all(), true);
    }

    public static function nativeName(string $locale): string
    {
        return (string) (config("portal.locale_names.{$locale}") ?? $locale);
    }

    /**
     * Locales to try, in order, for a missing string.
     *
     * @return list<string>
     */
    public static function fallbackChain(string $locale): array
    {
        $chain = (array) config("portal.fallbacks.{$locale}", [$locale]);
        $chain[] = (string) config('app.fallback_locale', 'en');

        return array_values(array_unique(array_map('strval', [$locale, ...$chain])));
    }

    /**
     * Best supported locale for an Accept-Language header, honouring q-values
     * and matching regional tags such as am-ET to am.
     */
    public static function fromAcceptLanguage(?string $header): ?string
    {
        if ($header === null || trim($header) === '') {
            return null;
        }

        $candidates = [];
        foreach (explode(',', $header) as $position => $part) {
            $pieces = array_map('trim', explode(';', $part));
            $tag = strtolower($pieces[0]);
            if ($tag === '' || $tag === '*') {
                continue;
            }

            $quality = 1.0;
            foreach (array_slice($pieces, 1) as $param) {
                if (str_starts_with($param, 'q=')) {
                    $quality = (float) substr($param, 2);
                }
            }
            if ($quality <= 0) {
                continue;
            }

            $primary = explode('-', str_replace('_', '-', $tag))[0];
            if (self::isSupported($primary)) {
                // Earlier entries win ties, as browsers list preferences in order.
                $candidates[] = ['locale' => $primary, 'q' => $quality, 'pos' => $position];
            }
        }

        usort($candidates, fn (array $a, array $b): int => [$b['q'], $a['pos']] <=> [$a['q'], $b['pos']]);

        return $candidates[0]['locale'] ?? null;
    }
}
