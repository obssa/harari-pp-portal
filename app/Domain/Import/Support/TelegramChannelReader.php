<?php

namespace App\Domain\Import\Support;

use App\Domain\Import\Models\ContentSource;
use Carbon\CarbonImmutable;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Reads a channel's public preview page, https://t.me/s/<channel>, which
 * Telegram shows without an account (docs/content/sources.md). Only that
 * address and photos on Telegram's CDN are ever requested.
 */
final class TelegramChannelReader
{
    public const USER_AGENT = 'Mozilla/5.0 (compatible; HarariPPPortal/1.0; +https://hararipp.orcamind.dev)';

    private const PHOTO_HOST = '#^https://cdn\d*\.telesco\.pe/file/[A-Za-z0-9_\-./]+$#';

    /**
     * Messages on one page, oldest first; `$before` pages back in time.
     *
     * @return list<TelegramMessage>
     */
    public function page(string $channel, ?int $before = null): array
    {
        if (preg_match(ContentSource::HANDLE_PATTERN, $channel) !== 1) {
            throw new RuntimeException("Invalid channel name: {$channel}");
        }

        $response = Http::withUserAgent(self::USER_AGENT)
            ->timeout(20)
            ->retry(2, 1000, throw: false)
            ->get('https://t.me/s/'.$channel, array_filter(['before' => $before]));

        if (! $response->successful()) {
            throw new RuntimeException("Telegram answered {$response->status()} for {$channel}");
        }

        return $this->parse($channel, $response->body());
    }

    /**
     * @return list<TelegramMessage>
     */
    public function parse(string $channel, string $html): array
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($document);
        $messages = [];
        $nodes = $xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " tgme_widget_message ")][@data-post]');

        foreach ($nodes === false ? [] : $nodes as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }
            [$owner, $id] = array_pad(explode('/', $node->getAttribute('data-post'), 2), 2, '');
            if (strcasecmp($owner, $channel) !== 0 || ! ctype_digit($id)) {
                continue;
            }

            $time = $this->first($xpath, './/time[@datetime]', $node);
            if (! $time instanceof DOMElement) {
                continue;
            }

            $textNode = $this->first($xpath, './/div[contains(@class, "tgme_widget_message_text")]', $node);
            $photos = [];
            foreach ($xpath->query('.//a[contains(@class, "tgme_widget_message_photo_wrap")]', $node) ?: [] as $photo) {
                if ($photo instanceof DOMElement && preg_match("/background-image:url\\('([^']+)'\\)/", $photo->getAttribute('style'), $m) === 1
                    && preg_match(self::PHOTO_HOST, $m[1]) === 1) {
                    $photos[] = $m[1];
                }
            }

            $messages[] = new TelegramMessage(
                channel: $channel,
                id: (int) $id,
                at: CarbonImmutable::parse($time->getAttribute('datetime')),
                text: $textNode instanceof DOMNode ? $this->text($textNode) : '',
                photos: array_values(array_unique($photos)),
                hasVideo: $this->first($xpath, './/*[contains(@class, "tgme_widget_message_video")]', $node) !== null,
            );
        }

        usort($messages, fn (TelegramMessage $a, TelegramMessage $b): int => $a->id <=> $b->id);

        return $messages;
    }

    private function first(DOMXPath $xpath, string $query, DOMNode $context): ?DOMNode
    {
        $nodes = $xpath->query($query, $context);

        return $nodes === false ? null : $nodes->item(0);
    }

    /** The message text with line breaks kept and markup removed. */
    private function text(DOMNode $node): string
    {
        $text = '';
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement && strtolower($child->tagName) === 'br') {
                $text .= "\n";
            } elseif ($child instanceof DOMElement) {
                $text .= $this->text($child);
            } else {
                $text .= $child->textContent;
            }
        }

        $text = (string) preg_replace("/[ \t\x{00A0}]+/u", ' ', $text);
        $text = (string) preg_replace("/ *\n */", "\n", $text);

        return trim((string) preg_replace("/\n{3,}/", "\n\n", $text));
    }
}
