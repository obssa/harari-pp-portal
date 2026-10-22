<?php

namespace App\Filament\Resources\HeroSlides\Pages;

use App\Domain\Homepage\Actions\SaveHeroSlide;
use App\Domain\Homepage\Models\HeroSlide;
use App\Filament\Resources\HeroSlides\HeroSlideResource;
use App\Filament\Support\CurrentUser;
use App\Filament\Support\ViewOnSite;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditHeroSlide extends EditRecord
{
    protected static string $resource = HeroSlideResource::class;

    protected function getHeaderActions(): array
    {
        return [
            HeroSlideResource::previewAction()->record($this->getRecord()),
            ViewOnSite::site(),
            DeleteAction::make(),
        ];
    }

    /**
     * Buttons come back as the form's parts (kind, target, style, labels).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['layout'] = $data['layout'] instanceof \BackedEnum ? $data['layout']->value : $data['layout'];
        $data['buttons'] = array_map(
            fn (array $button): array => [
                ...SaveHeroSlide::decompose($button['link'] ?? null),
                'style' => $button['style'] ?? 'primary',
                'label' => $button['label'] ?? [],
            ],
            (array) ($data['buttons'] ?? []),
        );

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var HeroSlide $record */
        return app(SaveHeroSlide::class)->handle($record, $data, CurrentUser::get());
    }
}
