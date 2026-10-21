<?php

namespace App\Domain\Engagement\Mail;

use App\Domain\Engagement\Models\Campaign;
use App\Domain\Engagement\Models\CampaignDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * A campaign email in one language (ADR-0052, ADR-0053). The body is Markdown
 * written in the admin, without raw HTML; every email names the sender,
 * carries an unsubscribe link and the headers mail apps use for their own
 * button. Its links pass through the portal so clicks are counted; an open
 * image is added only when open counting is switched on.
 */
final class CampaignMail extends Mailable
{
    use Queueable;

    public function __construct(
        public readonly Campaign $campaign,
        public readonly string $language,
        public readonly ?CampaignDelivery $delivery,
    ) {}

    /** The email's body as HTML in a language, as written. */
    public static function bodyHtml(Campaign $campaign, string $language): string
    {
        return Str::markdown((string) ($campaign->content[$language]['body'] ?? ''), ['html_input' => 'strip', 'allow_unsafe_links' => false]);
    }

    /**
     * The web links in the body, in order: the click link of a recipient names one by its position.
     *
     * @return list<string>
     */
    public static function links(Campaign $campaign, string $language): array
    {
        preg_match_all('/href="(https?:\/\/[^"]+)"/i', self::bodyHtml($campaign, $language), $matches);

        return array_map(fn (string $url): string => html_entity_decode($url, ENT_QUOTES | ENT_HTML5), $matches[1]);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: (string) ($this->campaign->content[$this->language]['subject'] ?? $this->campaign->name));
    }

    public function headers(): Headers
    {
        return $this->delivery === null ? new Headers : new Headers(text: [
            'List-Unsubscribe' => '<'.$this->delivery->unsubscribeUrl().'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
    }

    public function content(): Content
    {
        $html = self::bodyHtml($this->campaign, $this->language);
        $delivery = $this->delivery;
        if ($delivery !== null) {
            $position = 0;
            $html = (string) preg_replace_callback('/href="(https?:\/\/[^"]+)"/i', function () use (&$position, $delivery): string {
                return 'href="'.e(route('campaigns.click', ['locale' => $delivery->locale, 'token' => $delivery->token, 'link' => $position++])).'"';
            }, $html);
            if (config('portal.campaign_open_tracking')) {
                $html .= '<img src="'.e(route('campaigns.open', ['locale' => $delivery->locale, 'token' => $delivery->token])).'" width="1" height="1" alt="">';
            }
        }

        return new Content(markdown: 'mail.campaign', with: [
            'body' => new HtmlString($html),
            'unsubscribe' => $delivery?->unsubscribeUrl(),
        ]);
    }
}
