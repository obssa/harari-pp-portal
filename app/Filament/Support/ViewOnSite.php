<?php

namespace App\Filament\Support;

use App\Domain\Content\Models\Post;
use App\Domain\Translation\Support\Locales;
use App\Support\PublicLinks;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

/**
 * "View on site" in the admin (ADR-0068): opens what a record looks like on
 * the public site, in a new tab, in each language that is published. Before
 * anything is published the button says so (posts point to their preview
 * link).
 */
final class ViewOnSite
{
    /**
     * Header actions of an edit page: the published languages, or a note
     * that nothing is on the site yet. The group has no record of its own;
     * it hides itself when every language in it is hidden.
     *
     * @return list<Action|ActionGroup>
     */
    public static function header(): array
    {
        $languages = array_map(
            fn (string $locale): Action => Action::make("viewOnSite_{$locale}")
                ->label(Locales::nativeName($locale))
                ->url(fn (Model $record): ?string => PublicLinks::for($record)[$locale] ?? null, shouldOpenInNewTab: true)
                ->visible(fn (Model $record): bool => isset(PublicLinks::for($record)[$locale])),
            Locales::all(),
        );

        return [
            ActionGroup::make($languages)
                ->label(__('admin.view_on_site.label'))
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->button(),
            Action::make('viewOnSiteUnpublished')
                ->label(__('admin.view_on_site.label'))
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->disabled()
                ->tooltip(fn (Model $record): string => $record instanceof Post ? __('admin.view_on_site.draft_post') : __('admin.view_on_site.draft'))
                ->visible(fn (Model $record): bool => PublicLinks::for($record) === []),
        ];
    }

    /**
     * A row action for lists: the record on the site, in the admin's
     * language where it is published there.
     */
    public static function row(): Action
    {
        return Action::make('viewOnSite')
            ->label(__('admin.view_on_site.label'))
            ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
            ->iconButton()
            ->tooltip(__('admin.view_on_site.label'))
            ->color('gray')
            ->url(fn (Model $record): ?string => PublicLinks::preferred($record), shouldOpenInNewTab: true)
            ->visible(fn (Model $record): bool => PublicLinks::for($record) !== []);
    }

    /**
     * For pages that change the whole site (homepage, menus, settings).
     */
    public static function site(): Action
    {
        return Action::make('viewSite')
            ->label(__('admin.view_on_site.site'))
            ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
            ->color('gray')
            ->url(fn (): string => route('home', ['locale' => Locales::isSupported(app()->getLocale()) ? app()->getLocale() : Locales::default()]), shouldOpenInNewTab: true);
    }
}
