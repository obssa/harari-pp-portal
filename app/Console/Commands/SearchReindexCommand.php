<?php

namespace App\Console\Commands;

use App\Domain\Content\Models\PageTranslation;
use App\Domain\Content\Models\PostTranslation;
use App\Domain\Organisation\Models\LeaderTranslation;
use App\Domain\Organisation\Models\LeagueTranslation;
use App\Domain\Organisation\Models\OfficeTranslation;
use App\Domain\Search\SiteSearch;
use App\Domain\Search\Support\SearchText;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * Rebuilds the text site search matches against (ADR-0020), e.g. after the
 * matching rules change. With Meilisearch, run `scout:import` afterwards.
 */
final class SearchReindexCommand extends Command
{
    protected $signature = 'portal:search-reindex';

    protected $description = 'Rebuild the search text of every post, page, leader, office and league language';

    public function handle(): int
    {
        foreach (SiteSearch::KINDS as $kind => $spec) {
            $model = $spec['model'];
            $count = 0;
            $model::query()->chunkById(200, function (Collection $translations) use (&$count): void {
                foreach ($translations as $translation) {
                    /** @var PostTranslation|PageTranslation|LeaderTranslation|OfficeTranslation|LeagueTranslation $translation */
                    $translation->forceFill(['search_text' => SearchText::build($translation->searchableParts())])->saveQuietly();
                    $count++;
                }
            });
            $this->line("{$kind}: {$count}");
        }

        return self::SUCCESS;
    }
}
