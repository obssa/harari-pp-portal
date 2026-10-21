@php
    $field = 'mt-1 block w-full rounded-control border border-line bg-surface px-3 py-2 focus:border-primary-600 focus:outline-none focus:ring-2 focus:ring-primary-600/30';
    $button = 'inline-flex items-center rounded-control bg-primary-600 px-5 py-2.5 font-semibold text-white hover:bg-primary-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600';
@endphp
<x-layouts.public :title="__('donations.title')" :description="__('donations.description')">
    <x-page-hero :title="__('donations.title')" :lead="$intro ?? __('donations.description')" tone="plaster" :crumbs="[[__('nav.get_involved'), route('get-involved')]]" />
    <div class="mx-auto max-w-page px-4 pt-2 pb-12 sm:px-6">
        <div class="max-w-2xl">

            <form method="post" action="{{ route('donations.store') }}" class="mt-8 space-y-6" novalidate>
                @csrf
                <div>
                    <label for="name" class="font-medium">{{ __('donations.field.name') }}</label>
                    <input id="name" name="name" type="text" value="{{ old('name') }}" required maxlength="120" autocomplete="name"
                           @error('name') aria-invalid="true" aria-describedby="name-error" @enderror class="{{ $field }}">
                    @error('name')<p id="name-error" class="mt-1 text-sm text-danger-600">{{ $message }}</p>@enderror
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="phone" class="font-medium">{{ __('donations.field.phone') }}</label>
                        <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" maxlength="20" autocomplete="tel" inputmode="tel" dir="ltr"
                               @error('phone') aria-invalid="true" aria-describedby="phone-error" @enderror class="{{ $field }}">
                        @error('phone')<p id="phone-error" class="mt-1 text-sm text-danger-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="email" class="font-medium">{{ __('donations.field.email') }}</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" maxlength="190" autocomplete="email" dir="ltr"
                               @error('email') aria-invalid="true" aria-describedby="email-error" @enderror class="{{ $field }}">
                        @error('email')<p id="email-error" class="mt-1 text-sm text-danger-600">{{ $message }}</p>@enderror
                    </div>
                </div>
                <p class="-mt-3 text-sm text-ink-muted">{{ __('donations.contact_help') }}</p>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="amount" class="font-medium">{{ __('donations.field.amount') }} ({{ $settings->currency }})</label>
                        <input id="amount" name="amount" type="number" value="{{ old('amount') }}" required min="{{ $settings->minimum }}" step="1" inputmode="decimal" dir="ltr"
                               @error('amount') aria-invalid="true" aria-describedby="amount-error" @enderror class="{{ $field }}">
                        @error('amount')<p id="amount-error" class="mt-1 text-sm text-danger-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="purpose" class="font-medium">{{ __('donations.field.purpose') }}</label>
                        <input id="purpose" name="purpose" type="text" value="{{ old('purpose') }}" maxlength="200"
                               @error('purpose') aria-invalid="true" aria-describedby="purpose-error" @enderror class="{{ $field }}">
                        @error('purpose')<p id="purpose-error" class="mt-1 text-sm text-danger-600">{{ $message }}</p>@enderror
                    </div>
                </div>

                <label class="flex items-start gap-3">
                    <input type="checkbox" name="anonymous" value="1" @checked(old('anonymous')) class="mt-1 size-5 rounded border-line text-primary-600">
                    <span>{{ __('donations.field.anonymous') }}</span>
                </label>

                <div>
                    <label class="flex items-start gap-3">
                        <input type="checkbox" name="consent" value="1" @checked(old('consent')) required class="mt-1 size-5 rounded border-line text-primary-600"
                               @error('consent') aria-invalid="true" aria-describedby="consent-error" @enderror>
                        <span>{{ __('donations.field.consent') }} <a href="{{ route('page.show', ['path' => 'privacy']) }}" class="text-link underline underline-offset-4">{{ __('membership.privacy_link') }}</a></span>
                    </label>
                    @error('consent')<p id="consent-error" class="mt-1 text-sm text-danger-600">{{ $message }}</p>@enderror
                </div>

                <x-forms.protection :turnstile-key="$turnstileKey" />

                <button type="submit" class="{{ $button }}">{{ __('donations.submit') }}</button>
            </form>
        </div>
    </div>
</x-layouts.public>
