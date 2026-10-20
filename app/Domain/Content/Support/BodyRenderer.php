<?php

namespace App\Domain\Content\Support;

use App\Domain\Content\Blocks\CalloutBlock;
use App\Domain\Content\Blocks\FileBlock;
use App\Domain\Content\Blocks\GalleryBlock;
use App\Domain\Content\Blocks\ImageBlock;
use App\Domain\Content\Blocks\StatisticsBlock;
use App\Domain\Content\Blocks\VideoBlock;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Support\Str;

/**
 * Turns the block editor's JSON (ADR-0012) into safe HTML for the public
 * site, plain text for search and counts, and the list of library files the
 * body uses (for usage tracking).
 */
final class BodyRenderer
{
    /** Blocks offered in the editor and rendered on the site. */
    public const BLOCKS = [
        ImageBlock::class,
        GalleryBlock::class,
        VideoBlock::class,
        CalloutBlock::class,
        FileBlock::class,
        StatisticsBlock::class,
    ];

    /** Block id => config keys holding media ids. */
    private const MEDIA_KEYS = [
        'image' => ['media_id'],
        'gallery' => ['media_ids'],
        'file' => ['media_id'],
    ];

    /**
     * @param  array<string, mixed>|null  $body
     */
    public static function toHtml(?array $body): string
    {
        if (self::isEmpty($body) || $body === null) {
            return '';
        }

        // Editors' text goes through Filament's HTML sanitizer. Our block
        // views (escaped Blade, iframes and <picture> the sanitizer would
        // drop) are rendered separately and put back in place of a marker.
        $marker = 'hppblock'.Str::lower(Str::random(16));
        $blocks = [];
        $document = self::replaceBlocks($body, function (array $node) use ($marker, &$blocks): array {
            $token = $marker.count($blocks);
            $blocks[$token] = self::renderBlock($node);

            return ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $token]]];
        });

        $html = RichContentRenderer::make($document)->toHtml();
        foreach ($blocks as $token => $blockHtml) {
            $html = str_replace('<p>'.$token.'</p>', $blockHtml, $html);
        }

        return $html;
    }

    /**
     * @param  array<mixed>  $node
     */
    private static function renderBlock(array $node): string
    {
        $attrs = (array) ($node['attrs'] ?? []);
        foreach (self::BLOCKS as $block) {
            if ($block::getId() === ($attrs['id'] ?? null)) {
                return (string) $block::toHtml((array) ($attrs['config'] ?? []), []);
            }
        }

        return '';
    }

    /**
     * A copy of the document with every custom block replaced.
     *
     * @param  array<mixed>  $node
     * @param  callable(array<mixed>): array<mixed>  $replace
     * @return array<mixed>
     */
    private static function replaceBlocks(array $node, callable $replace): array
    {
        if (($node['type'] ?? null) === 'customBlock') {
            return $replace($node);
        }

        if (isset($node['content']) && is_array($node['content'])) {
            $node['content'] = array_map(
                fn (mixed $child): mixed => is_array($child) ? self::replaceBlocks($child, $replace) : $child,
                $node['content'],
            );
        }

        return $node;
    }

    /**
     * @param  array<string, mixed>|null  $body
     */
    public static function toText(?array $body): string
    {
        if (self::isEmpty($body)) {
            return '';
        }

        return trim(RichContentRenderer::make($body)->customBlocks(self::BLOCKS)->toText());
    }

    /**
     * Words in Latin or Ethiopic script (Ethiopic words are separated by
     * spaces or the word separator ፡).
     *
     * @param  array<string, mixed>|null  $body
     */
    public static function wordCount(?array $body): int
    {
        return (int) preg_match_all('/[\p{L}\p{M}\p{N}]+/u', self::toText($body));
    }

    /**
     * Library media ids used by blocks in the body.
     *
     * @param  array<string, mixed>|null  $body
     * @return list<int>
     */
    public static function mediaIds(?array $body): array
    {
        $ids = [];
        self::walk($body ?? [], function (array $node) use (&$ids): void {
            if (($node['type'] ?? null) !== 'customBlock') {
                return;
            }

            $attrs = (array) ($node['attrs'] ?? []);
            $config = (array) ($attrs['config'] ?? []);
            foreach (self::MEDIA_KEYS[$attrs['id'] ?? ''] ?? [] as $key) {
                foreach ((array) ($config[$key] ?? []) as $id) {
                    if (is_numeric($id)) {
                        $ids[] = (int) $id;
                    }
                }
            }
        });

        return array_values(array_unique($ids));
    }

    /**
     * @param  array<string, mixed>|null  $body
     */
    public static function isEmpty(?array $body): bool
    {
        return $body === null || ($body['content'] ?? []) === [];
    }

    /**
     * @param  array<mixed>  $node
     * @param  callable(array<mixed>): void  $visit
     */
    private static function walk(array $node, callable $visit): void
    {
        $visit($node);
        foreach ((array) ($node['content'] ?? []) as $child) {
            if (is_array($child)) {
                self::walk($child, $visit);
            }
        }
    }
}
