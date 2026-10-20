<?php

namespace App\Domain\Analytics\Mail;

use App\Domain\Analytics\Support\ReportCsv;
use App\Domain\Analytics\Support\TrafficReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * A scheduled traffic report (ADR-0040): the top rows in the message and all
 * rows as a CSV attachment. Counts only; nothing about any visitor.
 */
final class AnalyticsReportMail extends Mailable implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<array{key: string, label: string, locale: ?string, views: int, share: float}>  $rows
     */
    public function __construct(
        public readonly string $name,
        public readonly string $dimension,
        public readonly string $period,
        public readonly int $total,
        public readonly array $rows,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('analytics.mail.subject', ['name' => $this->name, 'period' => $this->period]));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.analytics.report', with: [
            'name' => $this->name,
            'dimension' => TrafficReport::dimensionLabel($this->dimension),
            'period' => $this->period,
            'total' => $this->total,
            'rows' => array_slice($this->rows, 0, 20),
        ]);
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        return [Attachment::fromData(fn (): string => ReportCsv::make($this->dimension, $this->rows), 'traffic-'.$this->dimension.'.csv')->withMime('text/csv')];
    }
}
