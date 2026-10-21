@php
    $field = 'mt-1 block w-full rounded-control border border-line bg-surface px-3 py-2 focus:border-primary-600 focus:outline-none focus:ring-2 focus:ring-primary-600/30';
    $button = 'inline-flex items-center rounded-control bg-primary-600 px-5 py-2.5 font-semibold text-white hover:bg-primary-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600';
@endphp
<x-layouts.public :title="__('members.portal.code_title')">
    <div class="mx-auto max-w-page px-4 py-10 sm:px-6">
        <div class="max-w-xl">
            <h1 class="heading-1">{{ __('members.portal.code_title') }}</h1>
            <p role="status" class="mt-4 text-lg">{{ __('members.portal.code_help', ['phone' => $phone, 'minutes' => \App\Domain\Membership\Actions\SignInMember::CODE_MINUTES]) }}</p>
            <form method="post" action="{{ route('member.verify-code') }}" class="mt-8 space-y-6" novalidate>
                @csrf
                <div>
                    <label for="code" class="font-medium">{{ __('membership.field.code') }}</label>
                    <input id="code" name="code" type="text" required maxlength="10" autocomplete="one-time-code" inputmode="numeric" dir="ltr"
                           @error('code') aria-invalid="true" aria-describedby="code-error" @enderror class="{{ $field }} max-w-48 font-mono text-xl tracking-widest">
                    @error('code')<p id="code-error" class="mt-1 text-sm text-danger-600">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="{{ $button }}">{{ __('members.portal.sign_in') }}</button>
            </form>
            <p class="mt-8"><a href="{{ route('member.sign-in') }}" class="font-semibold text-link underline underline-offset-4">{{ __('members.portal.other_number') }}</a></p>
        </div>
    </div>
</x-layouts.public>
