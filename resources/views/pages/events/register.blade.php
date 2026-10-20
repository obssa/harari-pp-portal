@use('App\Domain\Events\Models\Event')
@php
    $open = in_array($state, [Event::REGISTRATION_OPEN, Event::REGISTRATION_WAITLIST], true);
    $field = 'mt-1 block w-full rounded-control border border-line bg-surface px-3 py-2 focus:border-primary-600 focus:outline-none focus:ring-2 focus:ring-primary-600/30';
@endphp
<x-layouts.public :title="__('events.registration.title', ['event' => $translation->title])" :description="$translation->summary" :alternates="$alternates">
    <div class="mx-auto max-w-page px-4 py-10 sm:px-6">
        <nav aria-label="{{ __('news.breadcrumb') }}" class="text-sm text-ink-muted">
            <ol class="flex flex-wrap gap-1">
                <li><a href="{{ route('events.index') }}" class="hover:underline">{{ __('events.title') }}</a> /</li>
                <li><a href="{{ route('events.show', ['slug' => $translation->slug]) }}" class="hover:underline">{{ $translation->title }}</a></li>
            </ol>
        </nav>

        <div class="mt-6 grid gap-10 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <h1 class="heading-1">{{ __('events.registration.title', ['event' => $translation->title]) }}</h1>
                <p class="mt-3 text-ink-muted"><x-date :date="$event->starts_at" :time="! $event->all_day" />@if (filled($translation->venue)) · {{ $translation->venue }}@endif</p>

                @if (session('status'))
                    <p role="status" class="mt-6 rounded-card border border-primary-600/40 bg-primary-50 px-4 py-3 text-primary-800 dark:bg-primary-950 dark:text-primary-200">{{ session('status') }}</p>
                @endif

                @if ($state === Event::REGISTRATION_WAITLIST)
                    <p class="mt-6 rounded-card border border-line bg-surface-muted px-4 py-3">{{ __('events.registration.waitlist_note') }}</p>
                @elseif ($state === Event::REGISTRATION_FULL)
                    <p class="mt-6 rounded-card border border-line bg-surface-muted px-4 py-3">{{ __('events.registration.full') }}</p>
                @elseif ($state === Event::REGISTRATION_CLOSED)
                    <p class="mt-6 rounded-card border border-line bg-surface-muted px-4 py-3">{{ __('events.registration.closed') }}</p>
                @elseif ($placesLeft !== null)
                    <p class="mt-6 font-medium">{{ trans_choice('events.registration.places_left', $placesLeft, ['count' => $placesLeft]) }}</p>
                @endif

                @if ($open)
                    <form method="post" action="{{ route('events.register.store', ['slug' => $translation->slug]) }}" class="mt-8 max-w-xl space-y-5" novalidate>
                        @csrf
                        @error('form')
                            <p role="alert" class="rounded-card border border-danger-600/40 bg-danger-50 px-4 py-3 text-danger-800">{{ $message }}</p>
                        @enderror

                        <div>
                            <label for="name" class="font-medium">{{ __('events.registration.field.name') }}</label>
                            <input id="name" name="name" type="text" value="{{ old('name') }}" required maxlength="120" autocomplete="name"
                                   @error('name') aria-invalid="true" aria-describedby="name-error" @enderror class="{{ $field }}">
                            @error('name')<p id="name-error" class="mt-1 text-sm text-danger-600">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="phone" class="font-medium">{{ __('events.registration.field.phone') }}</label>
                            <p id="phone-help" class="text-sm text-ink-muted">{{ __('events.registration.help.phone') }}</p>
                            <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" maxlength="20" autocomplete="tel" inputmode="tel" dir="ltr"
                                   aria-describedby="phone-help @error('phone') phone-error @enderror" @error('phone') aria-invalid="true" @enderror class="{{ $field }}">
                            @error('phone')<p id="phone-error" class="mt-1 text-sm text-danger-600">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="email" class="font-medium">{{ __('events.registration.field.email') }}</label>
                            <input id="email" name="email" type="email" value="{{ old('email') }}" maxlength="190" autocomplete="email" dir="ltr"
                                   @error('email') aria-invalid="true" aria-describedby="email-error" @enderror class="{{ $field }}">
                            @error('email')<p id="email-error" class="mt-1 text-sm text-danger-600">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <div class="flex items-start gap-3">
                                <input id="consent" name="consent" type="checkbox" value="1" @checked(old('consent')) required
                                       @error('consent') aria-invalid="true" aria-describedby="consent-error" @enderror class="mt-1 size-5 rounded border-line text-primary-600">
                                <label for="consent">{{ __('events.registration.field.consent', ['days' => config('portal.registration_retention_days')]) }}</label>
                            </div>
                            @error('consent')<p id="consent-error" class="mt-1 text-sm text-danger-600">{{ $message }}</p>@enderror
                        </div>

                        <x-forms.protection :turnstile-key="$turnstileKey" />

                        <button type="submit" class="inline-flex items-center rounded-control bg-primary-600 px-5 py-2.5 font-semibold text-white hover:bg-primary-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600">
                            {{ $state === Event::REGISTRATION_WAITLIST ? __('events.registration.join_waitlist') : __('events.registration.submit') }}
                        </button>
                    </form>
                @endif
            </div>

            <aside class="text-sm text-ink-muted" aria-label="{{ __('events.registration.privacy_title') }}">
                <h2 class="heading-3 text-ink">{{ __('events.registration.privacy_title') }}</h2>
                <p class="mt-2">{{ __('events.registration.privacy', ['days' => config('portal.registration_retention_days')]) }}</p>
            </aside>
        </div>
    </div>
</x-layouts.public>
