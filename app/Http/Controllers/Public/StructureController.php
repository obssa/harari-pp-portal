<?php

namespace App\Http\Controllers\Public;

use App\Domain\Organisation\Enums\OfficeType;
use App\Domain\Organisation\Models\Office;
use App\Domain\Organisation\Queries\PublicDirectory;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * The organisational structure (PLAN.md §4 About: org chart generated from
 * admin data): the office tree with each office's head, as nested
 * disclosure lists that work without JavaScript.
 */
final class StructureController extends Controller
{
    public function __invoke(): View
    {
        $locale = app()->getLocale();
        $offices = PublicDirectory::visible(Office::query(), $locale)
            ->with(['head.translations'])
            ->orderBy('sort')
            ->get();

        // Offices whose parent is not shown hang from the top of the chart.
        $shown = $offices->modelKeys();
        $byParent = [];
        foreach ($offices as $office) {
            $parent = $office->parent_id !== null && in_array($office->parent_id, $shown, true) ? $office->parent_id : '';
            $byParent[$parent][] = $office;
        }

        $order = array_flip(array_map(fn (OfficeType $type): string => $type->value, OfficeType::cases()));

        return view('pages.structure', [
            'tree' => $this->branch($byParent, '', $order),
        ]);
    }

    /**
     * @param  array<string, list<Office>>  $byParent
     * @param  array<string, int>  $order
     * @return list<array{office: Office, children: list<mixed>}>
     */
    private function branch(array $byParent, string $parentId, array $order, int $depth = 0): array
    {
        if ($depth > Office::MAX_DEPTH) {
            return [];
        }

        $offices = $byParent[$parentId] ?? [];
        usort($offices, fn (Office $a, Office $b): int => [$order[$a->type->value], $a->sort] <=> [$order[$b->type->value], $b->sort]);

        return array_map(fn (Office $office): array => [
            'office' => $office,
            'children' => $this->branch($byParent, $office->id, $order, $depth + 1),
        ], $offices);
    }
}
