<?php

namespace App\Domain\Content\Blocks;

use App\Domain\Projects\Models\Statistic;
use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor\RichContentCustomBlock;
use Filament\Forms\Components\Select;

/**
 * Statistics in a page or post (PLAN.md §7: number blocks reusable in
 * pages, ADR-0027): the chosen numbers with their labels, units, sources
 * and as-of dates, in the reader's language.
 */
final class StatisticsBlock extends RichContentCustomBlock
{
    /** Most numbers in one block. */
    public const MAX = 8;

    public static function getId(): string
    {
        return 'statistics';
    }

    public static function getLabel(): string
    {
        return __('content.block.statistics');
    }

    public static function configureEditorAction(Action $action): Action
    {
        return $action->schema([
            Select::make('statistic_ids')
                ->label(__('content.block.field.statistics'))
                ->multiple()
                ->searchable()
                ->maxItems(self::MAX)
                ->required()
                ->options(fn (): array => Statistic::query()->with('translations')->orderBy('sort')->limit(500)->get()
                    ->mapWithKeys(fn (Statistic $s): array => [$s->id => $s->label().' ('.$s->formatted().')'])->all()),
        ]);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function getPreviewLabel(array $config): string
    {
        return __('content.block.statistics').': '.count(self::ids($config));
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $data
     */
    public static function toHtml(array $config, array $data): ?string
    {
        $ids = self::ids($config);
        $statistics = Statistic::query()->whereKey($ids)->with('translations')->get()
            ->sortBy(fn (Statistic $s): int => (int) array_search($s->id, $ids, true))->values();

        return $statistics->isEmpty() ? null : view('content.blocks.statistics', ['statistics' => $statistics])->render();
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<int>
     */
    private static function ids(array $config): array
    {
        $ids = array_map('intval', array_filter((array) ($config['statistic_ids'] ?? []), 'is_numeric'));

        return array_slice(array_values(array_unique($ids)), 0, self::MAX);
    }
}
