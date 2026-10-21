<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Domain\Content\Actions\DuplicatePost;
use App\Domain\Content\Actions\SavePost;
use App\Domain\Content\Models\Post;
use App\Domain\Content\Models\PostTranslation;
use App\Domain\Press\Actions\DistributePressRelease;
use App\Domain\Press\Models\MediaContact;
use App\Domain\Social\Actions\SharePost;
use App\Domain\Social\Models\SocialSource;
use App\Domain\Translation\Support\Locales;
use App\Filament\Resources\Posts\PostResource;
use App\Filament\Support\CurrentUser;
use App\Http\Controllers\Public\PreviewController;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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
            Action::make('sendToMedia')
                ->label(__('press.distribution.send'))
                ->icon(Heroicon::OutlinedNewspaper)
                ->color('gray')
                ->visible(fn (Post $record): bool => CurrentUser::get()->can('media_contacts.manage') && DistributePressRelease::canSend($record))
                ->modalDescription(__('press.distribution.send_help'))
                ->schema([
                    Select::make('contacts')->label(__('press.media.plural'))->multiple()->searchable()
                        ->helperText(__('press.distribution.contacts_help'))
                        ->options(fn (): array => MediaContact::query()->active()->orderBy('outlet')->get()
                            ->mapWithKeys(fn (MediaContact $c): array => [$c->id => $c->outlet.' · '.$c->name])->all()),
                ])
                ->action(function (array $data, Post $record): void {
                    $ids = array_map('intval', (array) ($data['contacts'] ?? []));
                    $count = app(DistributePressRelease::class)->send($record, $ids !== [] ? $ids : null, CurrentUser::get());
                    Notification::make()->success()->title(__('press.distribution.sending', ['count' => $count]))->send();
                }),
            Action::make('share')
                ->label(__('social.share.action'))
                ->icon(Heroicon::OutlinedShare)
                ->color('gray')
                ->visible(fn (Post $record): bool => CurrentUser::get()->can('social.share')
                    && SharePost::languages($record) !== [] && SharePost::accounts()->isNotEmpty())
                ->modalDescription(__('social.share.help'))
                ->schema([
                    CheckboxList::make('accounts')
                        ->label(__('social.share.accounts'))
                        ->options(fn (): array => SharePost::accounts()
                            ->mapWithKeys(fn (SocialSource $s): array => [$s->id => $s->platform->label().' · '.$s->name])->all())
                        ->required(),
                    Select::make('language')
                        ->label(__('newsletter.field.language'))
                        ->options(fn (Post $record): array => collect(SharePost::languages($record))
                            ->mapWithKeys(fn (string $l): array => [$l => Locales::nativeName($l)])->all())
                        ->default(fn (Post $record): ?string => SharePost::languages($record)[0] ?? null)
                        ->required(),
                    Textarea::make('message')
                        ->label(__('social.share.message'))
                        ->helperText(__('social.share.message_help'))
                        ->rows(4)
                        ->maxLength(3000),
                ])
                ->action(function (array $data, Post $record): void {
                    $count = app(SharePost::class)->share($record, array_map('strval', (array) $data['accounts']), (string) $data['language'], $data['message'] ?? null, CurrentUser::get());
                    Notification::make()->success()->title(__('social.share.queued', ['count' => $count]))->send();
                }),
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
