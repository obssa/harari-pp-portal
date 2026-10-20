<?php

namespace App\Domain\Events\Actions;

use App\Domain\Content\Actions\WriteTranslations;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventTranslation;
use App\Domain\Media\Actions\SyncMediaUsage;
use App\Domain\Organisation\Models\Leader;
use App\Domain\Organisation\Models\League;
use App\Domain\Organisation\Models\Office;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Saves an event with its languages, agenda and speakers (ADR-0025).
 */
final class SaveEvent
{
    /** Most lines in one agenda. */
    public const MAX_AGENDA = 40;

    public function __construct(
        private readonly WriteTranslations $translations,
        private readonly SyncMediaUsage $usage,
        private readonly FillFromWaitlist $waitlist,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Event $event, array $data, User $actor): Event
    {
        /** @var array<string, array<string, mixed>> $translations */
        $translations = (array) ($data['translations'] ?? []);
        WriteTranslations::requireName($translations, 'title');

        $starts = $this->time($data['starts_at'] ?? null);
        if ($starts === null) {
            throw ValidationException::withMessages(['data.starts_at' => __('events.validation.start')]);
        }
        $ends = $this->time($data['ends_at'] ?? null);
        if ($ends !== null && $ends->lessThan($starts)) {
            throw ValidationException::withMessages(['data.ends_at' => __('events.validation.end')]);
        }

        $online = trim((string) ($data['online_url'] ?? ''));
        if ($online !== '' && (! str_starts_with($online, 'https://') || filter_var($online, FILTER_VALIDATE_URL) === false)) {
            throw ValidationException::withMessages(['data.online_url' => __('organisation.validation.https')]);
        }

        [$latitude, $longitude] = $this->coordinates($data['latitude'] ?? null, $data['longitude'] ?? null);
        $capacity = filled($data['capacity'] ?? null) ? max(1, (int) $data['capacity']) : null;

        $saved = DB::transaction(function () use ($event, $data, $translations, $actor, $starts, $ends, $online, $latitude, $longitude, $capacity): Event {
            $event->fill([
                'starts_at' => $starts,
                'ends_at' => $ends,
                'all_day' => (bool) ($data['all_day'] ?? false),
                'latitude' => $latitude,
                'longitude' => $longitude,
                'online_url' => $online !== '' ? $online : null,
                'cover_id' => filled($data['cover_id'] ?? null) ? (int) $data['cover_id'] : null,
                'office_id' => filled($data['office_id'] ?? null) && Office::query()->whereKey($data['office_id'])->exists() ? (string) $data['office_id'] : null,
                'league_id' => filled($data['league_id'] ?? null) && League::query()->whereKey($data['league_id'])->exists() ? (string) $data['league_id'] : null,
                'registration_enabled' => (bool) ($data['registration_enabled'] ?? false),
                'capacity' => $capacity,
                'waitlist' => (bool) ($data['waitlist'] ?? true),
                'registration_closes_at' => $this->time($data['registration_closes_at'] ?? null),
            ]);
            if (! $event->exists) {
                $event->created_by = $actor->id;
            }
            $event->updated_by = $actor->id;
            $event->save();

            $this->translations->handle($event, $translations, ['summary', 'venue', 'address'], $actor->can('events.manage'), 'title');
            foreach ($event->translations()->get() as $translation) {
                /** @var EventTranslation $translation */
                $translation->agenda = $this->agenda($translations[$translation->locale]['agenda'] ?? null);
                $translation->save();
            }

            $speakers = array_values(array_unique(array_map('strval', (array) ($data['speakers'] ?? []))));
            $known = Leader::query()->whereKey($speakers)->pluck('id')->map(fn (mixed $id): string => (string) $id)->all();
            $sync = [];
            foreach (array_values(array_intersect($speakers, $known)) as $position => $id) {
                $sync[$id] = ['sort' => $position];
            }
            $event->speakers()->sync($sync);

            $this->usage->handle($event, 'cover', [$event->cover_id]);

            return $event->refresh();
        });

        $this->waitlist->handle($saved);

        return $saved;
    }

    /**
     * @return list<array{time: string, item: string}>|null
     */
    private function agenda(mixed $rows): ?array
    {
        $agenda = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $item = trim((string) (is_array($row) ? ($row['item'] ?? '') : ''));
            if ($item === '') {
                continue;
            }
            $agenda[] = ['time' => mb_substr(trim((string) ($row['time'] ?? '')), 0, 20), 'item' => mb_substr($item, 0, 300)];
        }

        return $agenda === [] ? null : array_slice($agenda, 0, self::MAX_AGENDA);
    }

    /**
     * @return array{0: ?float, 1: ?float}
     */
    private function coordinates(mixed $latitude, mixed $longitude): array
    {
        if (blank($latitude) && blank($longitude)) {
            return [null, null];
        }
        if (! is_numeric($latitude) || ! is_numeric($longitude) || abs((float) $latitude) > 90 || abs((float) $longitude) > 180) {
            throw ValidationException::withMessages(['data.latitude' => __('organisation.validation.coordinates')]);
        }

        return [round((float) $latitude, 6), round((float) $longitude, 6)];
    }

    private function time(mixed $value): ?CarbonImmutable
    {
        if (blank($value)) {
            return null;
        }

        try {
            return CarbonImmutable::parse((string) $value);
        } catch (Throwable) {
            throw ValidationException::withMessages(['data.starts_at' => __('organisation.validation.date')]);
        }
    }
}
