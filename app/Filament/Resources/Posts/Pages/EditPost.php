<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Domain\Content\Actions\DuplicatePost;
use App\Domain\Content\Actions\SavePost;
use App\Domain\Content\Models\Post;
use App\Domain\Content\Models\PostTranslation;
use App\Domain\Translation\Support\Locales;
use App\Filament\Resources\Posts\PostResource;
use App\Filament\Support\CurrentUser;
use App\Http\Controllers\Public\PreviewController;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

class EditPost extends EditRecord
{
    protected static string $resource = PostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->label(__('admin.posts.action.preview'))
                ->icon(Heroicon::OutlinedEye)
                ->color('gray')
                ->schema([
                    Select::make('language')
                        ->label(__('admin.revisions.field.language'))
                        ->options(fn (Post $record): array => collect($record->translatedLocales())
                            ->mapWithKeys(fn (string $l): array => [$l => Locales::nativeName($l)])->all())
                        ->required(),
                ])
                ->action(function (array $data, Post $record): void {
                    $language = (string) $data['language'];
                    Notification::make()
                        ->info()
                        ->persistent()
                        ->title(__('admin.posts.preview_ready', ['language' => Locales::nativeName($language)]))
                        ->body(PreviewController::url($record, $language))
                        ->send();
                }),
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
        $post->loadMissing(['translations', 'categories', 'tags', 'offices', 'leagues', 'leaders']);

        $data['categories'] = $post->categories->modelKeys();
        $data['tags'] = $post->tags->modelKeys();
        $data['offices'] = $post->offices->modelKeys();
        $data['leagues'] = $post->leagues->modelKeys();
        $data['leaders'] = $post->leaders->modelKeys();
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
