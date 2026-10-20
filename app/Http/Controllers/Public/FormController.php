<?php

namespace App\Http\Controllers\Public;

use App\Domain\Forms\Actions\SubmitForm;
use App\Domain\Forms\Models\Form;
use App\Domain\Forms\Models\FormSubmission;
use App\Domain\Forms\Models\FormTranslation;
use App\Domain\Organisation\Enums\OfficeType;
use App\Domain\Organisation\Models\Office;
use App\Domain\Organisation\Queries\PublicDirectory;
use App\Http\Controllers\Controller;
use App\Settings\SiteSettings;
use App\Support\SpamGuard;
use App\Support\Turnstile;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public forms, the contact page and ticket status (PLAN.md §4 Contact and
 * Get involved, ADR-0028). These pages keep a session for the form
 * protection and are never cached.
 */
final class FormController extends Controller
{
    public function show(string $slug, Turnstile $turnstile): View
    {
        [$form, $translation] = $this->find($slug);

        return view('pages.forms.show', [
            'form' => $form,
            'translation' => $translation,
            'turnstileKey' => $turnstile->siteKey(),
            'alternates' => PublicDirectory::alternates($form, 'forms.show', 'contact'),
        ]);
    }

    public function submit(Request $request, string $slug, SubmitForm $submit, Turnstile $turnstile): RedirectResponse
    {
        [$form, $translation] = $this->find($slug);

        SpamGuard::check($request);
        $turnstile->check($request);

        $uploads = array_filter((array) $request->file('answers', []), fn (mixed $file): bool => $file instanceof UploadedFile);
        $submission = $submit->handle($form, [
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'answers' => (array) $request->input('answers', []),
        ], $uploads, app()->getLocale());

        return redirect()->route('tickets.show', ['reference' => $submission->reference])
            ->with('status', filled($translation->success_message) ? $translation->success_message : __('forms.sent'));
    }

    public function contact(SiteSettings $settings, Turnstile $turnstile): View
    {
        $locale = app()->getLocale();
        $form = Form::query()->where('on_contact_page', true)->where('active', true)->with('translations')->first();
        $translation = $form !== null ? PublicDirectory::shown($form, $locale) : null;

        $offices = PublicDirectory::visible(Office::query(), $locale)
            ->where(fn ($q) => $q->whereNotNull('phone')->orWhereNotNull('email'))
            ->whereIn('type', [OfficeType::Branch->value, OfficeType::Woreda->value, OfficeType::Sector->value])
            ->with('translations')
            ->orderByRaw('type = ? desc', [OfficeType::Branch->value])->orderBy('sort')
            ->limit(30)->get();

        return view('pages.forms.contact', [
            'settings' => $settings,
            'offices' => $offices,
            'form' => $translation instanceof FormTranslation ? $form : null,
            'translation' => $translation instanceof FormTranslation ? $translation : null,
            'turnstileKey' => $turnstile->siteKey(),
        ]);
    }

    public function lookup(Request $request): View|RedirectResponse
    {
        $typed = trim((string) $request->query('reference', ''));
        if ($typed !== '') {
            $reference = FormSubmission::normaliseReference($typed);
            if ($reference !== '' && FormSubmission::query()->where('reference', $reference)->exists()) {
                return redirect()->route('tickets.show', ['reference' => $reference]);
            }
        }

        return view('pages.forms.lookup', ['typed' => $typed, 'notFound' => $typed !== '']);
    }

    public function ticket(string $reference): Response
    {
        $submission = FormSubmission::query()->where('reference', $reference)->with('form.translations')->first();
        abort_if($submission === null, 404);
        $translation = PublicDirectory::shown($submission->form, app()->getLocale());

        return response()->view('pages.forms.ticket', [
            'submission' => $submission,
            'title' => $translation instanceof FormTranslation ? $translation->title : $submission->form->title(),
        ])->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    /**
     * @return array{0: Form, 1: FormTranslation}
     */
    private function find(string $slug): array
    {
        /** @var FormTranslation|null $translation */
        $translation = FormTranslation::query()->where('locale', app()->getLocale())->where('slug', $slug)->first();
        $form = $translation?->form()->with('translations', 'office')->first();
        abort_if($translation === null || $form === null || ! $form->active || ! $translation->isLive(), 404);

        return [$form, $translation];
    }
}
