<?php

namespace App\Domain\Social\Actions;

use App\Domain\Notifications\Support\Recipients;
use App\Domain\Social\Enums\Platform;
use App\Domain\Social\Fetchers\FacebookFetcher;
use App\Domain\Social\Models\SocialSource;
use App\Domain\Social\Notifications\TokenExpiringNotice;
use App\Services\Calendar\EthiopianCalendar;
use App\Settings\IntegrationSettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Facebook page tokens run out (PLAN.md §5 token expiry warnings, ADR-0038).
 * Meta's debug_token tells when; social media managers are warned 14, 7 and 1
 * days before and when a token has expired or stopped working, once at each
 * stage. A token that cannot be checked (Meta unreachable) is left alone.
 */
final class CheckFacebookTokens
{
    /** Days before expiry at which to warn; 0 means expired or not working. */
    public const STAGES = [14, 7, 1, 0];

    public function __construct(private readonly IntegrationSettings $settings) {}

    /**
     * @return array{checked: int, warned: int}
     */
    public function handle(): array
    {
        $checked = 0;
        $warned = 0;
        foreach (SocialSource::query()->where('platform', Platform::Facebook->value)->get() as $source) {
            $token = $source->credentials['token'] ?? $this->settings->meta_page_access_token;
            if (blank($token)) {
                continue;
            }
            $state = self::inspect((string) $token);
            if ($state === null) {
                continue;
            }
            $checked++;
            $warned += $this->record($source, $state) ? 1 : 0;
        }

        return ['checked' => $checked, 'warned' => $warned];
    }

    /**
     * @return array{valid: bool, expires_at: ?CarbonImmutable}|null null when Meta could not be asked
     */
    public static function inspect(string $token): ?array
    {
        try {
            $response = Http::timeout(15)->get(FacebookFetcher::GRAPH.'debug_token', ['input_token' => $token, 'access_token' => $token]);
        } catch (Throwable) {
            return null;
        }
        $data = $response->json('data');
        if (! is_array($data)) {
            // Meta refuses to describe a token it no longer accepts.
            return $response->clientError() ? ['valid' => false, 'expires_at' => null] : null;
        }

        $ends = array_filter([(int) ($data['expires_at'] ?? 0), (int) ($data['data_access_expires_at'] ?? 0)]);

        return [
            'valid' => (bool) ($data['is_valid'] ?? false),
            'expires_at' => $ends === [] ? null : CarbonImmutable::createFromTimestamp(min($ends)),
        ];
    }

    /**
     * Stores what Meta said and warns when the token reached a new stage.
     *
     * @param  array{valid: bool, expires_at: ?CarbonImmutable}  $state
     */
    public function record(SocialSource $source, array $state): bool
    {
        $credentials = $source->credentials ?? [];
        $stage = self::stage($state);
        $previous = is_numeric($credentials['token_warned'] ?? null) ? (int) $credentials['token_warned'] : null;
        $credentials = [...$credentials,
            'token_valid' => $state['valid'],
            'token_expires_at' => $state['expires_at']?->toIso8601String(),
            'token_checked_at' => CarbonImmutable::now()->toIso8601String(),
            'token_warned' => $stage ?? null,
        ];
        $source->forceFill(['credentials' => $credentials])->save();

        if ($stage === null || ($previous !== null && $previous <= $stage)) {
            return false;
        }

        Recipients::notify(Recipients::withPermission('social.manage'), TokenExpiringNotice::for($source, $state['valid'] ? $state['expires_at'] : null));

        return true;
    }

    /** What the admin shows next to a saved token. */
    public static function describe(SocialSource $source): string
    {
        $credentials = $source->credentials ?? [];
        if (! array_key_exists('token_valid', $credentials)) {
            return __('social.source.token_unchecked');
        }
        if (! $credentials['token_valid']) {
            return __('social.source.token_invalid');
        }
        $expires = $credentials['token_expires_at'] ?? null;

        return is_string($expires)
            ? __('social.source.token_expires', ['date' => app(EthiopianCalendar::class)->format(CarbonImmutable::parse($expires))])
            : __('social.source.token_never');
    }

    /**
     * @param  array{valid: bool, expires_at: ?CarbonImmutable}  $state
     */
    private static function stage(array $state): ?int
    {
        if (! $state['valid']) {
            return 0;
        }
        if ($state['expires_at'] === null) {
            return null;
        }

        $days = (int) floor(CarbonImmutable::now()->diffInDays($state['expires_at'], false));
        foreach (array_reverse(self::STAGES) as $stage) {
            if ($days <= $stage) {
                return $stage;
            }
        }

        return null;
    }
}
