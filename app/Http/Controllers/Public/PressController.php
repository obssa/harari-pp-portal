<?php

namespace App\Http\Controllers\Public;

use App\Domain\Content\Enums\PostType;
use App\Domain\Content\Queries\PublicPosts;
use App\Domain\Events\Models\Event;
use App\Domain\Forms\Enums\FormType;
use App\Domain\Forms\Models\Form;
use App\Domain\Forms\Models\FormTranslation;
use App\Domain\Media\Models\Media;
use App\Domain\Organisation\Enums\LeaderBody;
use App\Domain\Organisation\Models\Leader;
use App\Domain\Organisation\Queries\PublicDirectory;
use App\Http\Controllers\Controller;
use App\Settings\PressKitSettings;
use Illuminate\Contracts\View\View;

/**
 * The press kit and the "Get involved" page (PLAN.md §4 News & Media and
 * Get involved, ADR-0029).
 */
final class PressController extends Controller
{
    /** The colours of the identity (PLAN.md §12), as journalists need them. */
    public const PALETTE = [
        'primary' => '#3151ED',
        'primary_dark' => '#2539C9',
        'accent' => '#CD6234',
        'ink' => '#0F172A',
    ];

    public function press(PressKitSettings $settings): View
    {
        $locale = app()->getLocale();
        $ids = array_values(array_filter($settings->logo_ids, 'is_int'));
        $logos = Media::query()->library()->whereKey($ids)->get()
            ->sortBy(fn (Media $m): int => (int) array_search($m->id, $ids, true))->values();

        $leaders = Leader::query()->where('body', LeaderBody::ExecutiveCommittee->value)->whereNotNull('portrait_id')
            ->with(['translations', 'portrait'])->orderBy('rank')->limit(12)->get()
            ->map(fn (Leader $leader): ?array => ($shown = PublicDirectory::shown($leader, $locale)) !== null ? ['leader' => $leader, 'translation' => $shown] : null)
            ->filter()->values();

        $form = $settings->form_id !== null ? Form::query()->whereKey($settings->form_id)->where('active', true)->with('translations')->first() : null;
        $formTranslation = $form !== null ? PublicDirectory::shown($form, $locale) : null;

        return view('pages.press', [
            'boilerplate' => self::pick($settings->boilerplate, $locale),
            'contactName' => self::pick($settings->contact_name, $locale),
            'settings' => $settings,
            'logos' => $logos,
            'leaders' => $leaders,
            'palette' => self::PALETTE,
            'form' => $formTranslation instanceof FormTranslation ? $formTranslation : null,
            'releases' => app(PublicPosts::class)->latest($locale, 5, PostType::PressRelease),
        ]);
    }

    public function getInvolved(): View
    {
        $locale = app()->getLocale();
        $feedback = Form::query()->whereIn('type', [FormType::Feedback->value, FormType::Petition->value])->where('active', true)
            ->with('translations')->get()
            ->map(fn (Form $form): ?FormTranslation => ($shown = PublicDirectory::shown($form, $locale)) instanceof FormTranslation ? $shown : null)
            ->filter()->values();

        return view('pages.get-involved', [
            'events' => PublicDirectory::visible(Event::query(), $locale)->upcoming()->orderBy('starts_at')->limit(3)->get(),
            'feedback' => $feedback,
        ]);
    }

    /**
     * @param  array<string, string>  $values
     */
    private static function pick(array $values, string $locale): ?string
    {
        foreach ([$locale, ...(array) config("portal.fallbacks.{$locale}", []), 'am', 'en'] as $candidate) {
            if (filled($values[$candidate] ?? null)) {
                return $values[$candidate];
            }
        }

        return null;
    }
}
