<?php

namespace App\Support;

/**
 * A short, readable name for a browser's user agent ("Chrome on Windows")
 * for sign-in alerts (ADR-0038). Deliberately rough: it names the browser
 * family and the system, nothing that identifies the device.
 */
final class UserAgent
{
    private const BROWSERS = [
        'Edg/' => 'Edge',
        'OPR/' => 'Opera',
        'SamsungBrowser/' => 'Samsung Internet',
        'Firefox/' => 'Firefox',
        'Chrome/' => 'Chrome',
        'Safari/' => 'Safari',
    ];

    private const SYSTEMS = [
        'Windows' => 'Windows',
        'Android' => 'Android',
        'iPhone' => 'iOS',
        'iPad' => 'iPadOS',
        'Mac OS X' => 'macOS',
        'CrOS' => 'ChromeOS',
        'Linux' => 'Linux',
    ];

    /**
     * @return array{browser: ?string, system: ?string}
     */
    public static function parts(?string $userAgent): array
    {
        $userAgent = (string) $userAgent;

        return [
            'browser' => self::first(self::BROWSERS, $userAgent),
            'system' => self::first(self::SYSTEMS, $userAgent),
        ];
    }

    public static function describe(?string $userAgent): string
    {
        ['browser' => $browser, 'system' => $system] = self::parts($userAgent);

        return match (true) {
            $browser !== null && $system !== null => __('access.sign_in_alert.device', ['browser' => $browser, 'system' => $system]),
            $browser !== null || $system !== null => (string) ($browser ?? $system),
            default => __('access.sign_in_alert.unknown_device'),
        };
    }

    /**
     * @param  array<string, string>  $needles
     */
    private static function first(array $needles, string $haystack): ?string
    {
        foreach ($needles as $needle => $name) {
            if (str_contains($haystack, $needle)) {
                return $name;
            }
        }

        return null;
    }
}
