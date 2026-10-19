<?php

namespace App\Domain\Import\Actions;

use App\Domain\Import\Models\ContentImport;
use App\Domain\Import\Models\ContentSource;
use App\Domain\Import\Support\MessageGroups;
use App\Domain\Import\Support\TelegramChannelReader;
use App\Domain\Import\Support\TelegramMessage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Reads each enabled source, the branch first, and imports the stories not
 * seen yet (docs/content/sources.md, ADR-0022). A source that fails keeps
 * its error for the admin and does not stop the others.
 */
final class RunImport
{
    public function __construct(
        private readonly TelegramChannelReader $reader,
        private readonly ImportStory $import,
    ) {}

    /**
     * @return array<string, array{imported: int, duplicates: int, skipped: int, error: ?string}>
     */
    public function handle(?ContentSource $only = null, int $pages = 1): array
    {
        $sources = ContentSource::query()
            ->where('enabled', true)
            ->when($only, fn ($q) => $q->whereKey($only->id))
            ->orderBy('priority')
            ->get();

        $report = [];
        foreach ($sources as $source) {
            $report[$source->handle] = $this->source($source, max(1, min(10, $pages)));
        }

        return $report;
    }

    /**
     * @return array{imported: int, duplicates: int, skipped: int, error: ?string}
     */
    private function source(ContentSource $source, int $pages): array
    {
        $counts = ['imported' => 0, 'duplicates' => 0, 'skipped' => 0, 'error' => null];

        try {
            $messages = $this->unseen($source, $pages);
            $groups = MessageGroups::build($messages);

            foreach ($groups['stories'] as $story) {
                $result = $this->import->handle($source, $story['text'], $story['media']);
                $result->status === ContentImport::DUPLICATE ? $counts['duplicates']++ : $counts['imported']++;
                foreach ($story['media'] as $media) {
                    $this->import->skip($source, $media);
                }
            }
            foreach ($groups['leftovers'] as $leftover) {
                $this->import->skip($source, $leftover);
                $counts['skipped']++;
            }

            $newest = collect($messages)->max(fn (TelegramMessage $m): int => $m->id);
            $source->forceFill([
                'cursor' => max((int) $source->cursor, (int) $newest),
                'last_fetched_at' => now(),
                'last_error' => null,
            ])->save();
        } catch (Throwable $e) {
            $counts['error'] = Str::limit($e->getMessage(), 500);
            $source->forceFill(['last_fetched_at' => now(), 'last_error' => $counts['error']])->save();
        }

        return $counts;
    }

    /**
     * Messages newer than the cursor and not yet recorded, oldest first.
     * Photos posted after their story was already imported arrive alone on
     * the next run and are skipped; staff can add them from the source link.
     *
     * @return list<TelegramMessage>
     */
    private function unseen(ContentSource $source, int $pages): array
    {
        $messages = [];
        $before = null;
        for ($page = 0; $page < $pages; $page++) {
            $batch = $this->reader->page($source->handle, $before);
            if ($batch === []) {
                break;
            }
            $messages = [...$batch, ...$messages];
            $oldest = $batch[0]->id;
            if ($source->cursor !== null && $oldest <= $source->cursor) {
                break;
            }
            $before = $oldest;
        }

        $known = ContentImport::query()
            ->where('content_source_id', $source->id)
            ->pluck('external_id')
            ->all();

        $fresh = array_values(array_filter(
            $messages,
            fn (TelegramMessage $m): bool => ($source->cursor === null || $m->id > $source->cursor) && ! in_array($m->externalId(), $known, true),
        ));
        usort($fresh, fn (TelegramMessage $a, TelegramMessage $b): int => $a->id <=> $b->id);

        return $fresh;
    }
}
