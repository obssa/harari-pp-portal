<?php

namespace App\Domain\Sites\Settings;

use App\Domain\Sites\Models\Site;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\LaravelSettings\SettingsRepositories\DatabaseSettingsRepository;

/**
 * Settings belong to a site (PLAN.md §7: `site_id` on content and settings
 * from day one). Every read and write is limited to the current site, so a
 * second site starts with its own settings instead of sharing the first's.
 */
final class SiteSettingsRepository extends DatabaseSettingsRepository
{
    /**
     * @return Builder<Model>
     */
    public function getBuilder(): Builder
    {
        return parent::getBuilder()->where('site_id', $this->siteId());
    }

    /**
     * @param  mixed  $payload
     */
    public function createProperty(string $group, string $name, $payload, bool $locked = false): void
    {
        parent::getBuilder()->create([
            'site_id' => $this->siteId(),
            'group' => $group,
            'name' => $name,
            'payload' => $this->encode($payload),
            'locked' => $locked,
        ]);
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    public function updatePropertiesPayload(string $group, array $properties): void
    {
        $siteId = $this->siteId();

        $rows = collect($properties)->map(fn (mixed $payload, string $name): array => [
            'site_id' => $siteId,
            'group' => $group,
            'name' => $name,
            'payload' => $this->encode($payload),
        ])->values()->all();

        parent::getBuilder()->upsert($rows, ['site_id', 'group', 'name'], ['payload']);
    }

    private function siteId(): int
    {
        return Site::current()->id;
    }
}
