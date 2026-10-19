<?php

namespace App\Domain\Content\Blocks;

use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\RichEditor\RichContentCustomBlock;
use Filament\Forms\Components\Textarea;

/**
 * A highlighted note inside a post (information, important, success).
 */
final class CalloutBlock extends RichContentCustomBlock
{
    public const TONES = ['info', 'warning', 'success'];

    public static function getId(): string
    {
        return 'callout';
    }

    public static function getLabel(): string
    {
        return __('content.block.callout');
    }

    public static function configureEditorAction(Action $action): Action
    {
        return $action->schema([
            Radio::make('tone')
                ->label(__('content.block.field.tone'))
                ->options(array_combine(self::TONES, array_map(fn (string $tone): string => __("content.block.tone.{$tone}"), self::TONES)))
                ->default('info')
                ->inline()
                ->required(),
            Textarea::make('text')
                ->label(__('content.block.field.text'))
                ->rows(3)
                ->maxLength(1000)
                ->required(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function getPreviewLabel(array $config): string
    {
        return __('content.block.callout');
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function toPreviewHtml(array $config): string
    {
        return '<p>'.e((string) ($config['text'] ?? '')).'</p>';
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $data
     */
    public static function toHtml(array $config, array $data): ?string
    {
        $text = trim((string) ($config['text'] ?? ''));
        if ($text === '') {
            return null;
        }

        $tone = in_array($config['tone'] ?? null, self::TONES, true) ? $config['tone'] : 'info';

        return view('content.blocks.callout', ['tone' => $tone, 'text' => $text])->render();
    }
}
