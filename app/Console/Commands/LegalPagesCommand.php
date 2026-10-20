<?php

namespace App\Console\Commands;

use App\Domain\Content\Actions\SavePage;
use App\Domain\Content\Models\Menu;
use App\Domain\Content\Models\Page;
use App\Domain\Content\Models\PageTranslation;
use App\Domain\Content\Support\LegalPages;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Creates the privacy policy, terms of use and accessibility statement as
 * published draft pages in English, Amharic and Afaan Oromo, and links them
 * in the footer (ADR-0043). Pages that already exist are left as they are,
 * so editors' changes are never overwritten.
 */
final class LegalPagesCommand extends Command
{
    protected $signature = 'portal:legal-pages {--as= : Email of the Super Admin to record as author (default: the first one)}';

    protected $description = 'Create the draft privacy, terms and accessibility pages and link them in the footer';

    public function handle(SavePage $save): int
    {
        $actor = filled($this->option('as'))
            ? User::query()->where('email', (string) $this->option('as'))->first()
            : User::query()->whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))->orderBy('id')->first();
        if (! $actor instanceof User || ! $actor->can('pages.publish')) {
            $this->error('No Super Admin to record as the author.');

            return self::FAILURE;
        }

        $pageIds = [];
        foreach (LegalPages::all() as $slug => $languages) {
            $existing = PageTranslation::query()->where('locale', 'en')->where('slug', $slug)->value('page_id');
            if ($existing !== null) {
                $this->line("{$slug}: already there, left unchanged.");
                $pageIds[$slug] = (string) $existing;

                continue;
            }

            $translations = [];
            foreach ($languages as $locale => $page) {
                $translations[$locale] = [
                    'title' => $page['title'],
                    'slug' => $slug,
                    'excerpt' => $page['excerpt'],
                    'body' => LegalPages::body($page),
                    'published' => true,
                ];
            }
            $created = $save->handle(new Page, ['template' => 'default', 'translations' => $translations], $actor);
            $pageIds[$slug] = (string) $created->id;
            $this->info("{$slug}: created in ".implode(', ', array_keys($languages)).'.');
        }

        $this->linkInFooter($pageIds, $actor);

        return self::SUCCESS;
    }

    /**
     * @param  array<string, string>  $pageIds
     */
    private function linkInFooter(array $pageIds, User $actor): void
    {
        $menu = Menu::query()->firstOrNew(['location' => 'footer']);
        $items = $menu->items ?? [];
        $linked = array_filter(array_map(fn (array $item): ?string => ($item['type'] ?? null) === 'page' ? (string) ($item['page_id'] ?? '') : null, $items));

        $pages = LegalPages::all();
        $added = 0;
        foreach ($pageIds as $slug => $id) {
            if (! in_array($id, $linked, true)) {
                $labels = array_map(fn (array $page): string => $page['title'], $pages[$slug] ?? []);
                $items[] = ['type' => 'page', 'page_id' => $id, 'labels' => $labels, 'children' => []];
                $added++;
            }
        }

        if ($added > 0) {
            $menu->fill(['items' => $items, 'updated_by' => $actor->id])->save();
            Menu::forgetCache();
        }
        $this->line("Footer: {$added} link(s) added.");
    }
}
