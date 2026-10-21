@php
    $field = 'mt-1 block w-full rounded-control border border-line bg-surface px-3 py-2 focus:border-primary-600 focus:outline-none focus:ring-2 focus:ring-primary-600/30';
    $button = 'inline-flex items-center rounded-control bg-primary-600 px-5 py-2.5 font-semibold text-white hover:bg-primary-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600';
@endphp
<x-layouts.public :title="__('members.portal.sign_in_title')" :description="__('members.portal.sign_in_help')">
    <div class="mx-auto max-w-page px-4 py-10 sm:px-6">
        <div class="max-w-xl">
            <h1 class="heading-1">{{ __('members.portal.sign_in_title') }}</h1>
            <p class="mt-2 text-lg text-ink-muted">{{ __('members.portal.sign_in_help') }}</p>
            @if (session('status'))
                <p role="status" class="mt-6 rounded-card border border-primary-600/40 bg-primary-50 px-4 py-3 text-primary-800 dark:bg-primary-950 dark:text-primary-200">{{ session('status') }}</p>
            @endif
            <form method="post" action="{{ route('member.send-code') }}" class="mt-8 space-y-6" novalidate>
                @csrf
                <div>
                    <label for="phone" class="font-medium">{{ __('membership.field.phone') }}</label>
                    <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" required maxlength="20" autocomplete="tel" inputmode="tel" dir="ltr"
                           @error('phone') aria-invalid="true" aria-describedby="phone-error" @enderror class="{{ $field }}">
                    @error('phone')<p id="phone-error" class="mt-1 text-sm text-danger-600">{{ $message }}</p>@enderror
                </div>
                <x-forms.protection :turnstile-key="$turnstileKey" />
                <button type="submit" class="{{ $button }}">{{ __('membership.send_code') }}</button>
            </form>
            @if ($fayda)
                <div class="mt-8 border-t border-line pt-8">
                    <p class="text-ink-muted">{{ __('members.fayda.or') }}</p>
                    <a href="{{ route('member.fayda') }}" class="mt-3 inline-flex items-center rounded-control border border-primary-600 px-5 py-2.5 font-semibold text-primary-700 hover:bg-primary-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 dark:text-primary-200 dark:hover:bg-primary-950">{{ __('members.fayda.sign_in') }}</a>
                    <p class="mt-2 text-sm text-ink-muted">{{ __('members.fayda.sign_in_help') }}</p>
                </div>
            @endif
            <p class="mt-8 text-ink-muted">{{ __('members.portal.not_member') }}
                <a href="{{ route('membership.apply') }}" class="font-semibold text-link underline underline-offset-4">{{ __('membership.apply.link') }}</a></p>
        </div>
    </div>
</x-layouts.public>
