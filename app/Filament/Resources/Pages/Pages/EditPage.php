<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Domain\Content\Actions\SavePage;
use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Models\Page;
use App\Domain\Content\Models\PageTranslation;
use App\Filament\Resources\Pages\PageResource;
use App\Filament\Support\CurrentUser;
use App\Filament\Support\ViewOnSite;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditPage extends EditRecord
{
    protected static string $resource = PageResource::class;

    protected function getHeaderActions(): array
    {
        return [...ViewOnSite::header(), DeleteAction::make(), RestoreAction::make()];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Page $page */
        $page = $this->getRecord();
        $data['translations'] = [];
        foreach ($page->translations()->get() as $translation) {
            /** @var PageTranslation $translation */
            $data['translations'][$translation->locale] = [
                ...$translation->only(['title', 'slug', 'excerpt', 'body', 'seo_title', 'seo_description', 'noindex']),
                'published' => $translation->status === ContentStatus::Published,
            ];
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Page $record */
        return app(SavePage::class)->handle($record, $data, CurrentUser::get());
    }
}
