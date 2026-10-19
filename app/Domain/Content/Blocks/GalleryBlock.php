<?php

namespace App\Domain\Content\Blocks;

use App\Domain\Media\Enums\MediaKind;
use App\Domain\Media\Models\Media;
use App\Filament\Support\MediaPicker;
use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor\RichContentCustomBlock;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Collection;

/**
 * A grid of library photos inside a post.
 */
final class GalleryBlock extends RichContentCustomBlock
{
    public const MAX_PHOTOS = 24;

    public static function getId(): string
    {
        return 'gallery';
    }

    public static function getLabel(): string
    {
        return __('content.block.gallery');
    }

    public static function configureEditorAction(Action $action): Action
    {
        return $action->schema([
            MediaPicker::make('media_ids', [MediaKind::Image], multiple: true)
                ->label(__('content.block.field.photos'))
                ->maxItems(self::MAX_PHOTOS)
                ->required(),
            TextInput::make('caption')
                ->label(__('content.block.field.caption'))
                ->maxLength(300),
        ]);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function getPreviewLabel(array $config): string
    {
        return __('content.block.gallery_count', ['count' => count((array) ($config['media_ids'] ?? []))]);
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $data
     */
    public static function toHtml(array $config, array $data): ?string
    {
        $photos = self::photos($config);
        if ($photos->isEmpty()) {
            return null;
        }

        return view('content.blocks.gallery', [
            'photos' => $photos,
            'caption' => (string) ($config['caption'] ?? ''),
        ])->render();
    }

    /**
     * Photos in the order the editor chose.
     *
     * @param  array<string, mixed>  $config
     * @return Collection<int, Media>
     */
    private static function photos(array $config): Collection
    {
        $ids = array_values(array_filter(array_map('intval', (array) ($config['media_ids'] ?? []))));
        $found = Media::query()->library()->whereIn('id', $ids)->get()->keyBy('id');

        return new Collection(array_values(array_filter(array_map(fn (int $id): ?Media => $found->get($id), $ids))));
    }
}
