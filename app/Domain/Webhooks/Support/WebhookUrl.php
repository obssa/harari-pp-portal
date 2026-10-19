<?php

namespace App\Domain\Webhooks\Support;

/**
 * Only public HTTPS addresses may receive webhooks, so the portal can never
 * be used to reach the server itself or a private network (SSRF).
 */
final class WebhookUrl
{
    public static function isAllowed(string $url): bool
    {
        $parts = parse_url($url);
        if ($parts === false || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
            return false;
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        $host = strtolower(trim($parts['host'], '[]'));
        if ($host === 'localhost' || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            return false;
        }

        $addresses = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : self::resolve($host);
        if ($addresses === []) {
            return false;
        }

        foreach ($addresses as $address) {
            if (! filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private static function resolve(string $host): array
    {
        $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];
        $addresses = [];
        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;
            if (is_string($ip)) {
                $addresses[] = $ip;
            }
        }

        return $addresses;
    }
}
