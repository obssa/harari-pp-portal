@use('App\Domain\Engagement\Support\SubscriptionOptions')
@php
    $field = 'mt-1 block w-full rounded-control border border-line bg-surface px-3 py-2 focus:border-primary-600 focus:outline-none focus:ring-2 focus:ring-primary-600/30';
    $button = 'inline-flex items-center rounded-control bg-primary-600 px-5 py-2.5 font-semibold text-white hover:bg-primary-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600';
    $channels = (array) old('channels', $email !== '' ? ['email'] : ['email', 'sms']);
    $topics = (array) old('topics', SubscriptionOptions::TOPICS);
@endphp
<x-layouts.public :title="__('newsletter.title')" :description="__('newsletter.description')">
    <x-page-hero :title="__('newsletter.title')" :lead="__('newsletter.description')" tone="plaster" :crumbs="[[__('nav.get_involved'), route('get-involved')]]" />
    <div class="mx-auto max-w-page px-4 pt-2 pb-12 sm:px-6">
        <div class="max-w-2xl">

            {{-- ADR-0077: an address from the footer is kept as given (another one needs "Use a different email"), and
                 each contact field shows only while its channel is ticked (CSS :has(); without it, both show). --}}
            <form method="post" action="{{ route('newsletter.store') }}" class="group mt-8 space-y-6" novalidate>
                @csrf
                @if ($fixedEmail)
                    <div class="rounded-card border border-line bg-surface-muted px-4 py-3">
                        <p>{{ __('newsletter.fixed_email', ['email' => $email]) }}</p>
                        <input type="hidden" name="email" value="{{ $email }}">
                        <input type="hidden" name="channels[]" value="email">
                        <p class="mt-1 text-sm"><a href="{{ route('newsletter.create') }}" class="text-link underline underline-offset-4">{{ __('newsletter.other_email') }}</a></p>
                    </div>
                    <label class="flex items-center gap-2">
                        <input id="channel-sms" type="checkbox" name="channels[]" value="sms" @checked(in_array('sms', $channels, true)) class="size-5 rounded border-line text-primary-600">
                        {{ __('newsletter.also_sms') }}
                    </label>
                @else
                    <fieldset @error('channels') aria-describedby="channels-error" @enderror>
                        <legend class="font-medium">{{ __('newsletter.field.channels') }}</legend>
                        <div class="mt-2 flex flex-wrap gap-6">
                            @foreach (SubscriptionOptions::channels() as $key => $label)
                                <label class="flex items-center gap-2">
                                    <input id="channel-{{ $key }}" type="checkbox" name="channels[]" value="{{ $key }}" @checked(in_array($key, $channels, true)) class="size-5 rounded border-line text-primary-600">
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                        @error('channels')<p id="channels-error" class="mt-1 text-sm text-danger-600">{{ $message }}</p>@enderror
                    </fieldset>
                @endif

                <div class="grid gap-4 sm:grid-cols-2">
                    @unless ($fixedEmail)
                        <div class="group-has-[#channel-email:not(:checked)]:hidden">
                            <label for="email" class="font-medium">{{ __('newsletter.field.email') }}</label>
                            <input id="email" name="email" type="email" value="{{ old('email', $email) }}" maxlength="190" autocomplete="email" dir="ltr"
                                   @error('email') aria-invalid="true" aria-describedby="email-error" @enderror class="{{ $field }}">
                            @error('email')<p id="email-error" class="mt-1 text-sm text-danger-600">{{ $message }}</p>@enderror
                        </div>
                    @endunless
                    @error('email')
                        @if ($fixedEmail)<p class="text-sm text-danger-600">{{ $message }}</p>@endif
                    @enderror
                    <div class="group-has-[#channel-sms:not(:checked)]:hidden">
                        <label for="phone" class="font-medium">{{ __('newsletter.field.phone') }}</label>
                        <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" maxlength="20" autocomplete="tel" inputmode="tel" dir="ltr"
                               @error('phone') aria-invalid="true" aria-describedby="phone-error" @enderror class="{{ $field }}">
                        @error('phone')<p id="phone-error" class="mt-1 text-sm text-danger-600">{{ $message }}</p>@enderror
                    </div>
                </div>

                <fieldset @error('topics') aria-describedby="topics-error" @enderror>
                    <legend class="font-medium">{{ __('newsletter.field.topics') }}</legend>
                    <div class="mt-2 grid gap-2 sm:grid-cols-2">
                        @foreach (SubscriptionOptions::topics() as $key => $label)
                            <label class="flex items-center gap-2">
                                <input type="checkbox" name="topics[]" value="{{ $key }}" @checked(in_array($key, $topics, true)) class="size-5 rounded border-line text-primary-600">
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                    @error('topics')<p id="topics-error" class="mt-1 text-sm text-danger-600">{{ $message }}</p>@enderror
                </fieldset>

                <p class="text-sm text-ink-muted">{{ __('newsletter.language_note') }}</p>

                <div>
                    <label class="flex items-start gap-3">
                        <input type="checkbox" name="consent" value="1" @checked(old('consent')) required class="mt-1 size-5 rounded border-line text-primary-600"
                               @error('consent') aria-invalid="true" aria-describedby="consent-error" @enderror>
                        <span>{{ __('newsletter.field.consent') }} <a href="{{ route('page.show', ['path' => 'privacy']) }}" class="text-link underline underline-offset-4">{{ __('membership.privacy_link') }}</a></span>
                    </label>
                    @error('consent')<p id="consent-error" class="mt-1 text-sm text-danger-600">{{ $message }}</p>@enderror
                </div>

                <x-forms.protection :turnstile-key="$turnstileKey" />

                <button type="submit" class="{{ $button }}">{{ __('newsletter.submit') }}</button>
            </form>
        </div>
    </div>
</x-layouts.public>
