<?php

namespace App\View\Components;

use App\Services\Calendar\EthiopianCalendar;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * <x-date :date="$post->published_at" /> renders a <time> element with the
 * machine-readable ISO date and the dual-calendar text for people.
 */
final class Date extends Component
{
    public ?string $iso = null;

    public ?string $text = null;

    public function __construct(
        DateTimeInterface|string|null $date,
        bool $time = false,
        ?string $calendar = null,
    ) {
        if ($date === null || $date === '') {
            return;
        }

        $value = $date instanceof DateTimeInterface ? CarbonImmutable::instance($date) : CarbonImmutable::parse($date);
        $this->iso = $value->toIso8601String();
        $this->text = app(EthiopianCalendar::class)->format($value, withTime: $time, only: $calendar);
    }

    public function render(): View|string
    {
        return $this->iso === null ? '' : view('components.date');
    }
}
