<?php

namespace App\Domain\Content\Blocks;

use App\Domain\Media\Enums\MediaKind;
use App\Domain\Media\Models\Media;
use App\Filament\Support\MediaPicker;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\RichEditor\RichContentCustomBlock;
use Filament\Forms\Components\TextInput;

/**
 * A photo from the media library inside a post, with caption and credit.
 */
final class ImageBlock extends RichContentCustomBlock
{
    public static function getId(): string
    {
        return 'image';
    }

    public static function getLabel(): string
    {
        return __('content.block.image');
    }

    public static function configureEditorAction(Action $action): Action
    {
        return $action->schema([
            MediaPicker::make('media_id', [MediaKind::Image, MediaKind::Vector])
                ->label(__('content.block.field.image'))
                ->required(),
            TextInput::make('caption')
                ->label(__('content.block.field.caption'))
                ->maxLength(300),
            Radio::make('width')
                ->label(__('content.block.field.width'))
                ->options(['normal' => __('content.block.width.normal'), 'wide' => __('content.block.width.wide')])
                ->default('normal')
                ->inline(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function getPreviewLabel(array $config): string
    {
        return __('content.block.image').(filled($config['caption'] ?? null) ? ': '.$config['caption'] : '');
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function toPreviewHtml(array $config): ?string
    {
        $media = self::media($config);
        $preview = $media?->adminPreviewUrl();

        return $preview === null ? null
            : '<img src="'.e($preview).'" alt="" style="max-height:10rem;border-radius:.5rem">';
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

        return view('content.blocks.image', [
            'media' => $media,
            'caption' => (string) ($config['caption'] ?? ''),
            'wide' => ($config['width'] ?? 'normal') === 'wide',
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
