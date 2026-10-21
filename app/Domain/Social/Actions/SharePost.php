<?php

namespace App\Domain\Social\Actions;

use App\Domain\Content\Models\Post;
use App\Domain\Content\Models\PostTranslation;
use App\Domain\Social\Enums\Platform;
use App\Domain\Social\Fetchers\FacebookFetcher;
use App\Domain\Social\Jobs\DeliverSocialShare;
use App\Domain\Social\Models\SocialShare;
use App\Domain\Social\Models\SocialSource;
use App\Models\User;
use App\Services\Telegram\TelegramBot;
use App\Settings\IntegrationSettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Shares a published post to the party's own Facebook pages and Telegram
 * channels from the admin (PLAN.md §13 Phase 8, ADR-0059), by staff with
 * `social.share`: in one language,
 * with the post's link, through the queue. Facebook uses the page's token
 * kept with the social source (or the one under Integrations); Telegram uses
 * the portal's bot, which must be an administrator of the channel.
 */
final class SharePost
{
    public const PLATFORMS = [Platform::Facebook, Platform::Telegram];

    /**
     * The accounts a post can be shared to.
     *
     * @return Collection<int, SocialSource>
     */
    public static function accounts(): Collection
    {
        return SocialSource::query()
            ->whereIn('platform', array_map(fn (Platform $p): string => $p->value, self::PLATFORMS))
            ->orderBy('platform')->orderBy('name')
            ->get();
    }

    /**
     * The languages the post is live in, for the share form.
     *
     * @return list<string>
     */
    public static function languages(Post $post): array
    {
        return array_values(array_filter(
            $post->translatedLocales(),
            fn (string $locale): bool => ($t = $post->translation($locale, fallback: false)) instanceof PostTranslation && $t->isLive(),
        ));
    }

    /** What is posted when staff do not write their own text: the title and the summary. */
    public static function defaultMessage(PostTranslation $translation): string
    {
        return trim($translation->title."\n\n".(string) $translation->excerpt);
    }

    /**
     * @param  list<string>  $sourceIds
     * @return int how many shares were queued
     */
    public function share(Post $post, array $sourceIds, string $locale, ?string $message, User $actor): int
    {
        abort_unless($actor->can('social.share'), 403);
        $translation = $post->translation($locale, fallback: false);
        if (! $translation instanceof PostTranslation || ! $translation->isLive()) {
            throw ValidationException::withMessages(['language' => __('social.share.validation.not_live')]);
        }

        $sources = self::accounts()->whereIn('id', $sourceIds);
        if ($sources->isEmpty()) {
            throw ValidationException::withMessages(['accounts' => __('social.share.validation.no_accounts')]);
        }

        $text = trim((string) $message) !== '' ? trim((string) $message) : self::defaultMessage($translation);
        $ids = [];
        foreach ($sources as $source) {
            $ids[] = SocialShare::query()->create([
                'post_id' => $post->id,
                'social_source_id' => $source->id,
                'platform' => $source->platform,
                'account' => $source->name,
                'locale' => $locale,
                'message' => mb_substr($text, 0, 3000),
                'status' => SocialShare::QUEUED,
                'shared_by' => $actor->id,
            ])->id;
        }

        activity('social')->performedOn($post)->causedBy($actor)->event('shared')
            ->withProperties(['accounts' => count($ids), 'locale' => $locale])->log('Post shared to social networks');
        DeliverSocialShare::dispatch($ids);

        return count($ids);
    }

    /** One share, from the queue. */
    public function deliver(SocialShare $share): void
    {
        $source = $share->source;
        $translation = $share->post->translation($share->locale, fallback: false);
        if ($source === null || ! $translation instanceof PostTranslation || ! $translation->isLive()) {
            $share->forceFill(['status' => SocialShare::FAILED, 'error' => __('social.share.error.gone')])->save();

            return;
        }

        $link = route('news.show', ['locale' => $share->locale, 'slug' => $translation->slug]);
        try {
            [$id, $url] = match ($source->platform) {
                Platform::Facebook => $this->toFacebook($source, $share->message, $link),
                Platform::Telegram => $this->toTelegram($source, $share->message, $link),
                default => throw new RuntimeException('Not a platform the portal posts to.'),
            };
            $share->forceFill([
                'status' => SocialShare::SENT, 'external_id' => $id, 'external_url' => $url,
                'error' => null, 'shared_at' => CarbonImmutable::now(),
            ])->save();
        } catch (Throwable $e) {
            $share->forceFill(['status' => SocialShare::FAILED, 'error' => mb_substr($e->getMessage(), 0, 190)])->save();
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function toFacebook(SocialSource $source, string $message, string $link): array
    {
        $token = $source->credentials['token'] ?? app(IntegrationSettings::class)->meta_page_access_token;
        if (! is_string($token) || $token === '') {
            throw new RuntimeException(__('social.error.facebook_token'));
        }

        $response = Http::asForm()->timeout(20)->post(FacebookFetcher::GRAPH.rawurlencode($source->handle).'/feed', [
            'message' => $message,
            'link' => $link,
            'access_token' => $token,
        ]);
        $id = $response->json('id');
        if (! $response->successful() || ! is_string($id)) {
            throw new RuntimeException('Facebook: '.mb_substr((string) ($response->json('error.message') ?? $response->status()), 0, 150));
        }

        return [$id, 'https://www.facebook.com/'.$id];
    }

    /**
     * @return array{0: string, 1: ?string}
     */
    private function toTelegram(SocialSource $source, string $message, string $link): array
    {
        $bot = app(TelegramBot::class);
        if (! $bot->isConfigured()) {
            throw new RuntimeException(__('social.share.error.telegram_bot'));
        }

        $handle = ltrim($source->handle, '@');
        $numeric = preg_match('/^-?\d+$/', $handle) === 1;
        $id = $bot->publish($numeric ? $handle : '@'.$handle, $message."\n\n".$link);

        return [(string) $id, $numeric ? null : 'https://t.me/'.$handle.'/'.$id];
    }
}
