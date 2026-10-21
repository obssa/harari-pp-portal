<?php

namespace App\Domain\Content\Actions;

use App\Domain\Access\Enums\SystemRole;
use App\Domain\Content\Models\ContentPackItem;
use App\Domain\Content\Models\Page;
use App\Domain\Content\Models\PostTranslation;
use App\Domain\Content\Workflow\ContentWorkflow;
use App\Domain\Events\Actions\SaveEvent;
use App\Domain\Events\Models\Event;
use App\Domain\Homepage\Actions\SaveHomepage;
use App\Domain\Homepage\Models\HomepageSection;
use App\Domain\Homepage\Support\HomepageLayout;
use App\Domain\Import\Models\ContentImport;
use App\Domain\Import\Models\ContentSource;
use App\Domain\Media\Actions\UploadMedia;
use App\Domain\Organisation\Actions\SaveLeader;
use App\Domain\Organisation\Actions\SaveLeague;
use App\Domain\Organisation\Actions\SaveOffice;
use App\Domain\Organisation\Models\Leader;
use App\Domain\Organisation\Models\League;
use App\Domain\Organisation\Models\Office;
use App\Filament\Pages\ManageHomepage;
use App\Models\User;
use App\Settings\SiteSettings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Loads the content pack (ADR-0063): real content gathered from public
 * sources, each record with its source, published at once. Every record is
 * loaded once, remembered by its key: what an editor later changes or
 * deletes is never put back. Runs on each deploy after the migrations.
 */
final class LoadContentPack
{
    /** @var array<string, int> */
    private array $counts = [];

    public function __construct(
        private readonly SaveLeague $leagues,
        private readonly SaveOffice $offices,
        private readonly SaveLeader $leaders,
        private readonly SavePage $pages,
        private readonly SaveEvent $events,
        private readonly SaveHomepage $homepage,
        private readonly UploadMedia $upload,
        private readonly ContentWorkflow $workflow,
    ) {}

    /**
     * @return array<string, int> records loaded, by kind
     */
    public function handle(string $path, ?User $actor = null): array
    {
        $pack = json_decode((string) @file_get_contents($path), true);
        if (! is_array($pack)) {
            throw new RuntimeException("The content pack at {$path} cannot be read.");
        }

        $actor ??= self::actor();
        if ($actor === null) {
            throw new RuntimeException('No Super Admin exists to load the content pack as.');
        }

        $this->counts = [];
        $photos = dirname($path).'/photos';

        foreach ((array) ($pack['leagues'] ?? []) as $item) {
            $this->once($item, 'leagues', fn (array $data): Model => $this->leagues->handle(new League, $data, $actor));
        }
        foreach ((array) ($pack['offices'] ?? []) as $item) {
            $this->once($item, 'offices', fn (array $data): Model => $this->offices->handle(new Office, [
                ...$data, 'parent_id' => $this->id($item['parent'] ?? null),
            ], $actor));
        }
        foreach ((array) ($pack['leaders'] ?? []) as $item) {
            $this->once($item, 'leaders', fn (array $data): Model => $this->leaders->handle(new Leader, [
                ...$data,
                'office_id' => $this->id($item['office'] ?? null),
                'league_id' => $this->id($item['league'] ?? null),
                'portrait_id' => $this->photo($photos, $item, $actor),
            ], $actor));
        }
        $this->heads($pack, $actor);
        foreach ((array) ($pack['pages'] ?? []) as $item) {
            $this->once($item, 'pages', fn (array $data): Model => $this->pages->handle(new Page, [
                ...$data, 'parent_id' => $this->id($item['parent'] ?? null),
            ], $actor));
        }
        foreach ((array) ($pack['events'] ?? []) as $item) {
            $this->once($item, 'events', fn (array $data): Model => $this->events->handle(new Event, $data, $actor));
        }

        $this->homepageSections((array) ($pack['homepage'] ?? []), $actor);
        $this->socialLinks((array) ($pack['settings']['social_links'] ?? []));
        $this->importedBacklog();

        return $this->counts;
    }

    /**
     * The first Super Admin, who signs the loaded records.
     */
    public static function actor(): ?User
    {
        return User::role(SystemRole::SuperAdmin->value)->orderBy('created_at')->first();
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  callable(array<string, mixed>): Model  $create
     */
    private function once(array $item, string $kind, callable $create): void
    {
        $key = (string) ($item['key'] ?? '');
        if ($key === '' || ContentPackItem::query()->where('key', $key)->exists()) {
            return;
        }

        DB::transaction(function () use ($item, $key, $kind, $create): void {
            $data = $item;
            $data['translations'] = array_map(
                fn (array $values): array => ['published' => true, ...$values],
                (array) ($item['translations'] ?? []),
            );
            $model = $create($data);
            ContentPackItem::remember($key, $model);
            $this->counts[$kind] = ($this->counts[$kind] ?? 0) + 1;
        });
    }

    private function id(mixed $key): ?string
    {
        return is_string($key) && $key !== '' ? ContentPackItem::query()->where('key', $key)->value('model_id') : null;
    }

    /**
     * Uploads a leader's portrait through the media pipeline, credited to
     * where it was published.
     *
     * @param  array<string, mixed>  $item
     */
    private function photo(string $folder, array $item, User $actor): ?int
    {
        $file = basename((string) ($item['photo'] ?? ''));
        if ($file === '' || ! is_file("{$folder}/{$file}")) {
            return null;
        }

        $media = $this->upload->handle("{$folder}/{$file}", $file, $actor);
        $alt = [];
        foreach ((array) ($item['translations'] ?? []) as $locale => $values) {
            $alt[$locale] = mb_substr((string) ($values['name'] ?? ''), 0, 250);
        }
        $media->forceFill(['alt' => $alt, 'credit' => parse_url((string) ($item['source_url'] ?? ''), PHP_URL_HOST) ?: null])->save();

        return $media->id;
    }

    /**
     * Office and league heads, set once both records exist and only where
     * no head has been chosen meanwhile.
     *
     * @param  array<string, mixed>  $pack
     */
    private function heads(array $pack, User $actor): void
    {
        foreach (['offices' => Office::class, 'leagues' => League::class] as $kind => $class) {
            foreach ((array) ($pack[$kind] ?? []) as $item) {
                $key = (string) ($item['key'] ?? '').'#head';
                $record = $class::query()->find($this->id($item['key'] ?? null));
                $head = $this->id($item['head'] ?? null);
                if ($record === null || $head === null || $record->head_leader_id !== null || ContentPackItem::query()->where('key', $key)->exists()) {
                    continue;
                }

                $record->forceFill(['head_leader_id' => $head, 'updated_by' => $actor->id])->save();
                ContentPackItem::remember($key, $record);
            }
        }
    }

    /**
     * The leader's message and the figures on the homepage, set once.
     *
     * @param  array<string, mixed>  $homepage
     */
    private function homepageSections(array $homepage, User $actor): void
    {
        $changes = [];
        $leadership = (array) ($homepage['leadership'] ?? []);
        $leaderId = $this->id($leadership['leader'] ?? null);
        if ($leaderId !== null && ! ContentPackItem::query()->where('key', 'homepage.leadership')->exists()) {
            $changes['leadership'] = ['enabled' => true, 'leader_id' => $leaderId, 'quote' => (array) ($leadership['quote'] ?? [])];
        }
        if (($homepage['counters'] ?? []) !== [] && ! ContentPackItem::query()->where('key', 'homepage.counters')->exists()) {
            $changes['counters'] = ['enabled' => true, 'counter_items' => (array) $homepage['counters']];
        }
        if ($changes === []) {
            return;
        }

        $states = HomepageLayout::all()->map(function (HomepageSection $section) use ($changes): array {
            $state = ManageHomepage::state($section);

            return [...$state, ...($changes[$section->key->value] ?? [])];
        })->values()->all();
        $this->homepage->handle($states, $actor);

        foreach (array_keys($changes) as $section) {
            ContentPackItem::remember("homepage.{$section}", null);
        }
        $this->counts['homepage'] = count($changes);
    }

    /**
     * Social links the site does not have yet; links an editor set stay.
     *
     * @param  array<string, string>  $links
     */
    private function socialLinks(array $links): void
    {
        if ($links === [] || ContentPackItem::query()->where('key', 'settings.social_links')->exists()) {
            return;
        }

        $settings = app(SiteSettings::class);
        $settings->social_links = [...$links, ...array_filter($settings->social_links)];
        $settings->save();
        ContentPackItem::remember('settings.social_links', null);
        $this->counts['settings'] = 1;
    }

    /**
     * Stories imported as drafts from a channel before it was trusted to
     * publish, published once with the date the channel posted them.
     */
    private function importedBacklog(): void
    {
        foreach (ContentSource::query()->where('auto_publish', true)->get() as $source) {
            $key = "import.backlog.{$source->handle}";
            if (ContentPackItem::query()->where('key', $key)->exists()) {
                continue;
            }

            $imports = ContentImport::query()
                ->where('content_source_id', $source->id)
                ->where('status', ContentImport::IMPORTED)
                ->whereNotNull('post_id')
                ->with('post.translations')
                ->get();
            foreach ($imports as $import) {
                $post = $import->post;
                if ($post === null) {
                    continue;
                }
                foreach ($post->translations as $translation) {
                    if ($translation instanceof PostTranslation && $this->workflow->publishImported($post, $translation, $import->message_at)) {
                        $this->counts['stories'] = ($this->counts['stories'] ?? 0) + 1;
                    }
                }
            }
            ContentPackItem::remember($key, null);
        }
    }
}
