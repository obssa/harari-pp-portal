<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Api\Support\ApiPresenter;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventTranslation;
use App\Domain\Organisation\Queries\PublicDirectory;
use App\Services\Calendar\EthiopianCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Events (scope `events`): upcoming by default, `when=past`, or the events
 * starting between `from` and `to` (dates, Ethiopian time).
 */
final class EventController extends ApiController
{
    public function __construct(private readonly ApiPresenter $present) {}

    public function index(Request $request): JsonResponse
    {
        $locale = $this->locale();
        $query = PublicDirectory::visible(Event::query(), $locale)->with(['cover', 'office.translations']);

        $from = $this->day($this->filter($request, 'from'));
        $to = $this->day($this->filter($request, 'to'));
        if ($from !== null || $to !== null) {
            $query->when($from, fn ($q, CarbonImmutable $day) => $q->where('starts_at', '>=', $day->utc()))
                ->when($to, fn ($q, CarbonImmutable $day) => $q->where('starts_at', '<', $day->addDay()->utc()))
                ->orderBy('starts_at');
        } elseif ($this->filter($request, 'when') === 'past') {
            $query->past()->orderByDesc('starts_at');
        } else {
            $query->upcoming()->orderBy('starts_at');
        }

        $page = $query->paginate($this->perPage($request))->withQueryString();

        return $this->page($page, fn (Event $event): ?array => ($shown = PublicDirectory::shown($event, $locale)) instanceof EventTranslation
            ? $this->present->event($event, $shown)
            : null);
    }

    public function show(string $key): JsonResponse
    {
        [$event, $shown] = $this->find(Event::query()->with(['cover', 'office.translations', 'speakers.translations', 'speakers.portrait']), $key);
        abort_unless($shown instanceof EventTranslation, 404);

        return $this->one($this->present->event($event, $shown, full: true));
    }

    private function day(?string $value): ?CarbonImmutable
    {
        if ($value === null || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('Y-m-d', $value, EthiopianCalendar::timezone())?->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }
}
