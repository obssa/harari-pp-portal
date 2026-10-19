<?php

namespace App\Domain\Organisation\Actions;

use App\Domain\Media\Actions\SyncMediaUsage;
use App\Domain\Organisation\Models\Leader;
use App\Domain\Organisation\Models\League;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Saves a league or forum with its languages (PLAN.md §7 Leagues & forums).
 */
final class SaveLeague
{
    public function __construct(
        private readonly WriteTranslations $translations,
        private readonly SyncMediaUsage $usage,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(League $league, array $data, User $actor): League
    {
        /** @var array<string, array<string, mixed>> $translations */
        $translations = (array) ($data['translations'] ?? []);
        WriteTranslations::requireName($translations);

        $headId = filled($data['head_leader_id'] ?? null) && Leader::query()->whereKey($data['head_leader_id'])->exists()
            ? (string) $data['head_leader_id'] : null;

        return DB::transaction(function () use ($league, $data, $translations, $actor, $headId): League {
            $league->fill([
                'head_leader_id' => $headId,
                'logo_id' => filled($data['logo_id'] ?? null) ? (int) $data['logo_id'] : null,
                'cover_image_id' => filled($data['cover_image_id'] ?? null) ? (int) $data['cover_image_id'] : null,
                'phone' => filled($data['phone'] ?? null) ? trim((string) $data['phone']) : null,
                'email' => WriteTranslations::email($data['email'] ?? null),
                'socials' => WriteTranslations::socials($data['socials'] ?? null, Leader::SOCIALS),
                'sort' => max(0, (int) ($data['sort'] ?? 0)),
            ]);
            if (! $league->exists) {
                $league->created_by = $actor->id;
            }
            $league->updated_by = $actor->id;
            $league->save();

            $this->translations->handle($league, $translations, ['summary'], $actor->can('leagues.manage'));
            $this->usage->handle($league, 'logo', [$league->logo_id]);
            $this->usage->handle($league, 'cover_image', [$league->cover_image_id]);

            return $league->refresh();
        });
    }
}
