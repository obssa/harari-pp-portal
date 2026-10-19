<?php

namespace App\Domain\Content\Blocks;

use App\Domain\Media\Enums\MediaKind;
use App\Domain\Media\Models\Media;
use App\Filament\Support\MediaPicker;
use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor\RichContentCustomBlock;
use Filament\Forms\Components\TextInput;

/**
 * A document, audio or video file from the media library to open or download.
 */
final class FileBlock extends RichContentCustomBlock
{
    public static function getId(): string
    {
        return 'file';
    }

    public static function getLabel(): string
    {
        return __('content.block.file');
    }

    public static function configureEditorAction(Action $action): Action
    {
        return $action->schema([
            MediaPicker::make('media_id', [MediaKind::Document, MediaKind::Audio, MediaKind::Video])
                ->label(__('content.block.field.file'))
                ->required(),
            TextInput::make('label')
                ->label(__('content.block.field.label'))
                ->helperText(__('content.block.help.label'))
                ->maxLength(200),
        ]);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function getPreviewLabel(array $config): string
    {
        $media = self::media($config);

        return __('content.block.file').': '.((string) ($config['label'] ?? '') ?: (string) $media?->name);
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $data
     */
    public static function toHtml(array $config, array $data): ?string
    {
        $media = self::media($config);
        if ($media === null) {
            return null;
        }

        return view('content.blocks.file', [
            'media' => $media,
            'label' => trim((string) ($config['label'] ?? '')) ?: $media->name,
        ])->render();
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function media(array $config): ?Media
    {
        $id = $config['media_id'] ?? null;

        return is_numeric($id) ? Media::query()->library()->find((int) $id) : null;
    }
}
