<?php

namespace App\Domain\Homepage\Actions;

use App\Domain\Content\Models\Post;
use App\Domain\Homepage\Enums\SectionKey;
use App\Domain\Homepage\Support\HomepageLayout;
use App\Domain\Organisation\Models\Leader;
use App\Domain\Translation\Support\Locales;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saves the homepage builder (PLAN.md §7): the order of the sections, which
 * are on, and each one's settings. Input is the builder's list of sections
 * in their new order; unknown keys and stray fields are dropped.
 */
final class SaveHomepage
{
    /**
     * @param  list<array<string, mixed>>  $sections
     */
    public function handle(array $sections, User $actor): void
    {
        $rows = [];
        foreach ($sections as $index => $state) {
            $key = SectionKey::tryFrom((string) ($state['key'] ?? ''));
            if ($key === null || isset($rows[$key->value])) {
                continue;
            }

            $rows[$key->value] = [
                'enabled' => (bool) ($state['enabled'] ?? false),
                'sort' => $index,
                'settings' => $this->settings($key, $state, $index),
                'curated_ids' => $key->takesCuratedPosts() ? $this->postIds($state['curated_ids'] ?? [], $key->maxItems()) : null,
            ];
        }

        DB::transaction(function () use ($rows, $actor): void {
            foreach (HomepageLayout::all() as $section) {
                $row = $rows[$section->key->value] ?? null;
                if ($row === null) {
                    continue;
                }
                $section->fill([...$row, 'updated_by' => $actor->id])->save();
            }
        });
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function settings(SectionKey $key, array $state, int $index): array
    {
        $settings = [
            'titles' => $this->texts($state['titles'] ?? [], 80),
            'locales' => array_values(array_intersect(Locales::all(), (array) ($state['locales'] ?? []))),
        ];

        if (in_array($key, [SectionKey::Ticker, SectionKey::Hero, SectionKey::LatestNews, SectionKey::Press, SectionKey::Leagues, SectionKey::Gallery], true)
            && filled($state['limit'] ?? null)) {
            $settings['limit'] = max(1, min($key->maxItems(), (int) $state['limit']));
        }

        if ($key === SectionKey::Leadership) {
            $leaderId = (string) ($state['leader_id'] ?? '');
            $settings['leader_id'] = $leaderId !== '' && Leader::query()->whereKey($leaderId)->exists() ? $leaderId : null;
            $settings['quote'] = $this->texts($state['quote'] ?? [], 400);
            $settings['post_id'] = $this->postIds([$state['post_id'] ?? null], 1)[0] ?? null;
        }

        if ($key === SectionKey::Counters) {
            $settings['items'] = $this->items($state['counter_items'] ?? [], $key->maxItems(), function (array $item): ?array {
                $value = trim((string) ($item['value'] ?? ''));
                $labels = $this->texts($item['label'] ?? [], 60);

                return $value !== '' && $labels !== [] ? ['value' => mb_substr($value, 0, 20), 'label' => $labels] : null;
            });
        }

        if ($key === SectionKey::Values) {
            $settings['items'] = $this->items($state['value_items'] ?? [], $key->maxItems(), function (array $item): ?array {
                $titles = $this->texts($item['title'] ?? [], 60);

                return $titles !== [] ? ['title' => $titles, 'text' => $this->texts($item['text'] ?? [], 240)] : null;
            });
        }

        if ($key === SectionKey::Partners) {
            $settings['items'] = $this->items($state['partner_items'] ?? [], $key->maxItems(), function (array $item) use ($index): ?array {
                $url = trim((string) ($item['url'] ?? ''));
                $labels = $this->texts($item['label'] ?? [], 80);
                if ($url === '' && $labels === []) {
                    return null;
                }
                if (! str_starts_with($url, 'https://') || filter_var($url, FILTER_VALIDATE_URL) === false || $labels === []) {
                    throw ValidationException::withMessages(["data.sections.{$index}.partner_items" => __('homepage.validation.partner')]);
                }

                return ['url' => $url, 'label' => $labels];
            });
        }

        return $settings;
    }

    /**
     * Texts per language, trimmed and cut to length; empty ones dropped.
     *
     * @return array<string, string>
     */
    private function texts(mixed $values, int $max): array
    {
        $texts = [];
        foreach (Locales::all() as $locale) {
            $text = trim((string) (is_array($values) ? ($values[$locale] ?? '') : ''));
            if ($text !== '') {
                $texts[$locale] = mb_substr($text, 0, $max);
            }
        }

        return $texts;
    }

    /**
     * @param  callable(array<string, mixed>): ?array<string, mixed>  $clean
     * @return list<array<string, mixed>>
     */
    private function items(mixed $items, int $max, callable $clean): array
    {
        $kept = [];
        foreach (is_array($items) ? $items : [] as $item) {
            $item = is_array($item) ? $clean($item) : null;
            if ($item !== null) {
                $kept[] = $item;
            }
        }

        return array_slice($kept, 0, $max);
    }

    /**
     * Ids of existing posts, in the given order, without repeats.
     *
     * @return list<string>
     */
    private function postIds(mixed $ids, int $max): array
    {
        $ids = array_values(array_unique(array_filter(array_map('strval', is_array($ids) ? $ids : []))));
        $existing = Post::query()->whereKey($ids)->pluck('id')->map(fn (mixed $id): string => (string) $id)->all();

        return array_slice(array_values(array_filter($ids, fn (string $id): bool => in_array($id, $existing, true))), 0, $max);
    }
}
