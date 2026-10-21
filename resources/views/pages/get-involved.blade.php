@use('App\Domain\Organisation\Queries\PublicDirectory')
@php
    $locale = app()->getLocale();
    $card = 'flex flex-col rounded-card border border-line bg-surface p-5';
@endphp
<x-layouts.public :title="__('involved.title')" :description="__('involved.description')">
    <div class="mx-auto max-w-page px-4 py-10 sm:px-6">
        <h1 class="heading-1">{{ __('involved.title') }}</h1>
        <p class="mt-2 max-w-prose text-lg text-ink-muted">{{ __('involved.description') }}</p>

        <ul class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <li class="{{ $card }}">
                <h2 class="heading-3">{{ __('involved.events.title') }}</h2>
                <p class="mt-2 text-ink-muted">{{ __('involved.events.text') }}</p>
                @if ($events->isNotEmpty())
                    <ul class="mt-4 space-y-2 text-sm">
                        @foreach ($events as $event)
                            @php $shown = PublicDirectory::shown($event, $locale); @endphp
                            @continue($shown === null)
                            <li lang="{{ $shown->locale }}">
                                <a href="{{ route('events.show', ['locale' => $shown->locale, 'slug' => $shown->slug]) }}" class="font-medium text-link underline-offset-4 hover:underline">{{ $shown->getAttribute('title') }}</a>
                                <span class="block text-ink-muted"><x-date :date="$event->starts_at" /></span>
                            </li>
                        @endforeach
                    </ul>
                @endif
                <a href="{{ route('events.index') }}" class="mt-auto pt-4 text-sm font-semibold text-link underline-offset-4 hover:underline">{{ __('involved.events.link') }}</a>
            </li>

            <li class="{{ $card }}">
                <h2 class="heading-3">{{ __('involved.feedback.title') }}</h2>
                <p class="mt-2 text-ink-muted">{{ __('involved.feedback.text') }}</p>
                <ul class="mt-4 space-y-2 text-sm">
                    @foreach ($feedback as $form)
                        <li lang="{{ $form->locale }}"><a href="{{ route('forms.show', ['locale' => $form->locale, 'slug' => $form->slug]) }}" class="font-medium text-link underline-offset-4 hover:underline">{{ $form->title }}</a></li>
                    @endforeach
                </ul>
                <a href="{{ route('contact') }}" class="mt-auto pt-4 text-sm font-semibold text-link underline-offset-4 hover:underline">{{ __('involved.feedback.link') }}</a>
            </li>

            <li class="{{ $card }}">
                <h2 class="heading-3">{{ __('involved.vacancies.title') }}</h2>
                <p class="mt-2 text-ink-muted">{{ __('involved.vacancies.text') }}</p>
                <a href="{{ route('documents.notices') }}" class="mt-auto pt-4 text-sm font-semibold text-link underline-offset-4 hover:underline">{{ __('involved.vacancies.link') }}</a>
            </li>

            <li class="{{ $card }}">
                <h2 class="heading-3">{{ __('involved.membership.title') }}</h2>
                <p class="mt-2 text-ink-muted">{{ __('involved.membership.text') }}</p>
                <p class="mt-auto flex flex-wrap gap-x-4 pt-4 text-sm font-semibold">
                    <a href="{{ route('membership.apply') }}" class="text-link underline-offset-4 hover:underline">{{ __('membership.apply.link') }}</a>
                    <a href="{{ route('membership.status') }}" class="text-link underline-offset-4 hover:underline">{{ __('membership.status.link') }}</a>
                </p>
            </li>

            <li class="{{ $card }}">
                <h2 class="heading-3">{{ __('involved.volunteer.title') }}</h2>
                <p class="mt-2 text-ink-muted">{{ __('involved.volunteer.text') }}</p>
                <a href="{{ route('volunteer.create') }}" class="mt-auto pt-4 text-sm font-semibold text-link underline-offset-4 hover:underline">{{ __('volunteers.form.link') }}</a>
            </li>

            @foreach (['newsletter'] as $soon)
                <li class="{{ $card }} border-dashed">
                    <p><x-ui.badge>{{ __('involved.coming_soon') }}</x-ui.badge></p>
                    <h2 class="heading-3 mt-3">{{ __("involved.{$soon}.title") }}</h2>
                    <p class="mt-2 text-ink-muted">{{ __("involved.{$soon}.text") }}</p>
                </li>
            @endforeach
        </ul>
    </div>
</x-layouts.public>
