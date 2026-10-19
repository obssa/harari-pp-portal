<?php

namespace App\Domain\Organisation\Actions;

use App\Domain\Media\Actions\SyncMediaUsage;
use App\Domain\Organisation\Enums\LeaderBody;
use App\Domain\Organisation\Models\Leader;
use App\Domain\Organisation\Models\League;
use App\Domain\Organisation\Models\Office;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Saves a leader's profile with its languages (PLAN.md §7 Leaders).
 */
final class SaveLeader
{
    public function __construct(
        private readonly WriteTranslations $translations,
        private readonly SyncMediaUsage $usage,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Leader $leader, array $data, User $actor): Leader
    {
        /** @var array<string, array<string, mixed>> $translations */
        $translations = (array) ($data['translations'] ?? []);
        WriteTranslations::requireName($translations);

        $body = LeaderBody::tryFrom((string) ($data['body'] ?? ''));
        if ($body === null) {
            throw ValidationException::withMessages(['data.body' => __('organisation.validation.body_required')]);
        }

        $termStart = $this->date($data['term_start'] ?? null);
        $termEnd = $this->date($data['term_end'] ?? null);
        if ($termStart !== null && $termEnd !== null && $termEnd->lessThan($termStart)) {
            throw ValidationException::withMessages(['data.term_end' => __('organisation.validation.term_order')]);
        }

        $officeId = filled($data['office_id'] ?? null) && Office::query()->whereKey($data['office_id'])->exists() ? (string) $data['office_id'] : null;
        $leagueId = filled($data['league_id'] ?? null) && League::query()->whereKey($data['league_id'])->exists() ? (string) $data['league_id'] : null;

        return DB::transaction(function () use ($leader, $data, $translations, $actor, $body, $termStart, $termEnd, $officeId, $leagueId): Leader {
            $leader->fill([
                'body' => $body,
                'office_id' => $officeId,
                'league_id' => $leagueId,
                'rank' => max(0, (int) ($data['rank'] ?? 100)),
                'term_start' => $termStart,
                'term_end' => $termEnd,
                'portrait_id' => filled($data['portrait_id'] ?? null) ? (int) $data['portrait_id'] : null,
                'phone' => filled($data['phone'] ?? null) ? trim((string) $data['phone']) : null,
                'email' => WriteTranslations::email($data['email'] ?? null),
                'socials' => WriteTranslations::socials($data['socials'] ?? null, Leader::SOCIALS),
            ]);
            if (! $leader->exists) {
                $leader->created_by = $actor->id;
            }
            $leader->updated_by = $actor->id;
            $leader->save();

            $this->translations->handle($leader, $translations, ['position', 'summary'], $actor->can('leaders.manage'));
            $this->usage->handle($leader, 'portrait', [$leader->portrait_id]);

            return $leader->refresh();
        });
    }

    private function date(mixed $value): ?CarbonImmutable
    {
        if (blank($value)) {
            return null;
        }

        try {
            return CarbonImmutable::parse((string) $value)->startOfDay();
        } catch (Throwable) {
            throw ValidationException::withMessages(['data.term_start' => __('organisation.validation.date')]);
        }
    }
}
