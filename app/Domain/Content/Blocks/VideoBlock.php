<?php

namespace App\Domain\Content\Blocks;

use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor\RichContentCustomBlock;
use Filament\Forms\Components\TextInput;

/**
 * A YouTube video, embedded through YouTube's privacy-enhanced domain and
 * loaded only when scrolled into view (PLAN.md §4; allowed by the CSP).
 */
final class VideoBlock extends RichContentCustomBlock
{
    private const PATTERN = '~^https?://(?:www\.|m\.)?(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})~';

    public static function getId(): string
    {
        return 'video';
    }

    public static function getLabel(): string
    {
        return __('content.block.video');
    }

    public static function configureEditorAction(Action $action): Action
    {
        return $action->schema([
            TextInput::make('url')
                ->label(__('content.block.field.youtube_url'))
                ->helperText(__('content.block.help.youtube_url'))
                ->url()
                ->regex(self::PATTERN)
                ->required(),
            TextInput::make('title')
                ->label(__('content.block.field.video_title'))
                ->helperText(__('content.block.help.video_title'))
                ->maxLength(200)
                ->required(),
        ]);
    }

    public static function youtubeId(?string $url): ?string
    {
        return is_string($url) && preg_match(self::PATTERN, $url, $match) === 1 ? $match[1] : null;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function getPreviewLabel(array $config): string
    {
        return __('content.block.video').': '.($config['title'] ?? '');
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $data
     */
    public static function toHtml(array $config, array $data): ?string
    {
        $id = self::youtubeId($config['url'] ?? null);
        if ($id === null) {
            return null;
        }

        return view('content.blocks.video', [
            'id' => $id,
            'title' => (string) ($config['title'] ?? ''),
        ])->render();
    }
}
