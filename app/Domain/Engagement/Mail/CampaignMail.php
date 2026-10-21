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
 * A campaign email in one language (ADR-0052). The body is Markdown written
 * in the admin, without raw HTML; every email names the sender, carries an
 * unsubscribe link and the headers mail apps use for their own button.
 */
final class CampaignMail extends Mailable
{
    use Queueable;

    public function __construct(
        public readonly Campaign $campaign,
        public readonly string $language,
        public readonly ?CampaignDelivery $delivery,
    ) {}

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
        $body = (string) ($this->campaign->content[$this->language]['body'] ?? '');

        return new Content(markdown: 'mail.campaign', with: [
            'body' => new HtmlString(Str::markdown($body, ['html_input' => 'strip', 'allow_unsafe_links' => false])),
            'unsubscribe' => $this->delivery?->unsubscribeUrl(),
        ]);
    }
}
