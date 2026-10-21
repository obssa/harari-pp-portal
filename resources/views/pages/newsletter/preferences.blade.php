@use('App\Domain\Engagement\Support\SubscriptionOptions')
@use('App\Domain\Translation\Support\Locales')
@use('App\Support\PhoneNumber')
@php
    $button = 'inline-flex items-center rounded-control bg-primary-600 px-5 py-2.5 font-semibold text-white hover:bg-primary-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600';
    $secondary = 'inline-flex items-center rounded-control border border-line bg-surface px-5 py-2.5 font-semibold hover:bg-surface-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600';
    $unsubscribed = $subscriber->unsubscribed_at !== null;
    $contacts = array_filter(['email' => $subscriber->email, 'sms' => $subscriber->phone ? PhoneNumber::mask($subscriber->phone) : null]);
@endphp
<x-layouts.public :title="__('newsletter.preferences_title')">
    <div class="mx-auto max-w-page px-4 py-10 sm:px-6">
        <div class="max-w-2xl">
            <h1 class="heading-1">{{ __('newsletter.preferences_title') }}</h1>
            @if (session('status'))
                <p role="status" class="mt-6 rounded-card border border-primary-600/40 bg-primary-50 px-4 py-3 text-primary-800 dark:bg-primary-950 dark:text-primary-200">{{ session('status') }}</p>
            @elseif ($unsubscribed)
                <p role="status" class="mt-6 rounded-card border border-line bg-surface px-4 py-3">{{ __('newsletter.unsubscribed') }}</p>
            @endif

            <form method="post" action="{{ route('newsletter.update', ['token' => $token]) }}" class="mt-8 space-y-6" novalidate>
                @csrf
                <fieldset>
                    <legend class="font-medium">{{ __('newsletter.field.channels') }}</legend>
                    <div class="mt-2 space-y-2">
                        @foreach (SubscriptionOptions::channels() as $key => $label)
                            @if (isset($contacts[$key]))
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" name="channels[]" value="{{ $key }}" @checked(! $unsubscribed && in_array($key, $subscriber->channels, true)) class="size-5 rounded border-line text-primary-600">
                                    {{ $label }} <span class="text-ink-muted" dir="ltr">({{ $contacts[$key] }})</span>
                                    @if (($key === 'email' && ! $subscriber->email_confirmed_at) || ($key === 'sms' && ! $subscriber->phone_confirmed_at))
                                        <x-ui.badge>{{ __('newsletter.not_confirmed') }}</x-ui.badge>
                                    @endif
                                </label>
                            @endif
                        @endforeach
                    </div>
                </fieldset>
                <fieldset @error('topics') aria-describedby="topics-error" @enderror>
                    <legend class="font-medium">{{ __('newsletter.field.topics') }}</legend>
                    <div class="mt-2 grid gap-2 sm:grid-cols-2">
                        @foreach (SubscriptionOptions::topics() as $key => $label)
                            <label class="flex items-center gap-2">
                                <input type="checkbox" name="topics[]" value="{{ $key }}" @checked(in_array($key, $subscriber->topics, true)) class="size-5 rounded border-line text-primary-600">
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                    @error('topics')<p id="topics-error" class="mt-1 text-sm text-danger-600">{{ $message }}</p>@enderror
                </fieldset>
                <div>
                    <label for="locale" class="font-medium">{{ __('newsletter.field.language') }}</label>
                    <select id="locale" name="locale" class="mt-1 block rounded-control border border-line bg-surface px-3 py-2">
                        @foreach (Locales::all() as $locale)
                            <option value="{{ $locale }}" @selected($subscriber->locale === $locale) lang="{{ $locale }}">{{ Locales::nativeName($locale) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex flex-wrap gap-3">
                    <button type="submit" class="{{ $button }}">{{ __('newsletter.save') }}</button>
                    @unless ($unsubscribed)
                        <button type="submit" name="unsubscribe" value="1" class="{{ $secondary }}">{{ __('newsletter.unsubscribe') }}</button>
                    @endunless
                </div>
            </form>
        </div>
    </div>
</x-layouts.public>
