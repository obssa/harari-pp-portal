<?php

namespace App\Filament\Resources\HeroSlides\Pages;

use App\Domain\Homepage\Actions\SaveHeroSlide;
use App\Domain\Homepage\Models\HeroSlide;
use App\Filament\Resources\HeroSlides\HeroSlideResource;
use App\Filament\Support\CurrentUser;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateHeroSlide extends CreateRecord
{
    protected static string $resource = HeroSlideResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return app(SaveHeroSlide::class)->handle(new HeroSlide, $data, CurrentUser::get());
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
