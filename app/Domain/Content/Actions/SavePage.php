<?php

namespace App\Domain\Content\Actions;

use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Models\Menu;
use App\Domain\Content\Models\Page;
use App\Domain\Content\Models\PageTranslation;
use App\Domain\Content\Support\BodyRenderer;
use App\Domain\Media\Actions\SyncMediaUsage;
use App\Domain\Translation\Support\Locales;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saves a page with its languages (PLAN.md §7 Pages). Pages are published
 * per language by holders of `pages.publish`; others save drafts. Parents
 * cannot form loops and nesting stays within Page::MAX_DEPTH.
 */
final class SavePage
{
    public function __construct(
        private readonly RecordRevision $revisions,
        private readonly SyncMediaUsage $usage,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Page $page, array $data, User $actor): Page
    {
        /** @var array<string, array<string, mixed>> $translations */
        $translations = (array) ($data['translations'] ?? []);
        $hasTitle = collect($translations)->contains(fn (array $values): bool => trim((string) ($values['title'] ?? '')) !== '');
        if (! $hasTitle) {
            throw ValidationException::withMessages(['data.translations.en.title' => __('content.validation.title_required')]);
        }

        $parentId = filled($data['parent_id'] ?? null) ? (string) $data['parent_id'] : null;
        $this->assertParentAllowed($page, $parentId);

        return DB::transaction(function () use ($page, $data, $translations, $actor, $parentId): Page {
            $page->fill([
                'template' => in_array($data['template'] ?? null, Page::TEMPLATES, true) ? $data['template'] : 'default',
                'parent_id' => $parentId,
                'sort' => (int) ($data['sort'] ?? 0),
                'featured_image_id' => filled($data['featured_image_id'] ?? null) ? (int) $data['featured_image_id'] : null,
            ]);
            if (! $page->exists) {
                $page->created_by = $actor->id;
            }
            $page->updated_by = $actor->id;
            $page->save();

            $canPublish = $actor->can('pages.publish');
            $existing = $page->translations()->get()->keyBy('locale');

            foreach (Locales::all() as $locale) {
                if (! array_key_exists($locale, $translations)) {
                    continue;
                }
                $values = $translations[$locale];
                /** @var PageTranslation|null $current */
                $current = $existing->get($locale);
                $title = trim((string) ($values['title'] ?? ''));

                if ($title === '') {
                    if ($current !== null && ($canPublish || $current->status !== ContentStatus::Published)) {
                        $current->delete();
                    }

                    continue;
                }

                $wanted = ($values['published'] ?? false) ? ContentStatus::Published : ContentStatus::Draft;
                $status = $canPublish ? $wanted : ($current->status ?? ContentStatus::Draft);

                $translation = $page->putTranslation($locale, [
                    'title' => $title,
                    'slug' => filled($values['slug'] ?? null) ? trim((string) $values['slug']) : $current?->slug,
                    'excerpt' => filled($values['excerpt'] ?? null) ? trim((string) $values['excerpt']) : null,
                    'body' => is_array($values['body'] ?? null) ? $values['body'] : null,
                    'seo_title' => filled($values['seo_title'] ?? null) ? trim((string) $values['seo_title']) : null,
                    'seo_description' => filled($values['seo_description'] ?? null) ? trim((string) $values['seo_description']) : null,
                    'noindex' => (bool) ($values['noindex'] ?? false),
                    'status' => $status,
                ]);
                $this->revisions->handle($page, $translation);
            }

            $this->usage->handle($page, 'featured_image', [$page->featured_image_id]);
            $bodyIds = [];
            foreach ($page->translations()->get() as $translation) {
                $bodyIds = [...$bodyIds, ...BodyRenderer::mediaIds($translation->body)];
            }
            $this->usage->handle($page, 'body', $bodyIds);

            Menu::forgetCache();

            return $page->refresh();
        });
    }

    private function assertParentAllowed(Page $page, ?string $parentId): void
    {
        if ($parentId === null) {
            return;
        }

        $depth = 1;
        $cursor = Page::query()->find($parentId);
        while ($cursor !== null) {
            if ($page->exists && $cursor->id === $page->id) {
                throw ValidationException::withMessages(['data.parent_id' => __('content.validation.parent_loop')]);
            }
            $depth++;
            $cursor = $cursor->parent_id ? Page::query()->find($cursor->parent_id) : null;
        }

        if ($depth > Page::MAX_DEPTH) {
            throw ValidationException::withMessages(['data.parent_id' => __('content.validation.too_deep', ['max' => Page::MAX_DEPTH])]);
        }
    }
}
