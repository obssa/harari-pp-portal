<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Domain\Content\Actions\DuplicatePost;
use App\Domain\Content\Actions\SavePost;
use App\Domain\Content\Models\Post;
use App\Domain\Content\Models\PostTranslation;
use App\Filament\Resources\Posts\PostResource;
use App\Filament\Support\CurrentUser;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

class EditPost extends EditRecord
{
    protected static string $resource = PostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('duplicate')
                ->label(__('admin.posts.action.duplicate'))
                ->icon(Heroicon::OutlinedDocumentDuplicate)
                ->color('gray')
                ->authorize(fn (Post $record): bool => CurrentUser::get()->can('replicate', $record))
                ->requiresConfirmation()
                ->action(function (Post $record): void {
                    $copy = app(DuplicatePost::class)->handle($record);
                    $this->redirect(PostResource::getUrl('edit', ['record' => $copy]));
                }),
            DeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Post $post */
        $post = $this->getRecord();
        $post->loadMissing(['translations', 'categories', 'tags']);

        $data['categories'] = $post->categories->modelKeys();
        $data['tags'] = $post->tags->modelKeys();
        $data['translations'] = [];

        foreach ($post->translations as $translation) {
            /** @var PostTranslation $translation */
            $data['translations'][$translation->locale] = [
                'title' => $translation->title,
                'slug' => $translation->slug,
                'kicker' => $translation->kicker,
                'excerpt' => $translation->excerpt,
                'body' => $translation->body,
                'status' => $translation->status->value,
                'published_at' => $translation->published_at?->toDateTimeString(),
                'seo_title' => $translation->seo_title,
                'seo_description' => $translation->seo_description,
                'noindex' => $translation->noindex,
            ];
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Post $record */
        return app(SavePost::class)->handle($record, $data, CurrentUser::get());
    }
}
