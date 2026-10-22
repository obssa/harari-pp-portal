@use('App\Domain\Membership\Enums\ApplicationStatus')
@php
    $field = 'mt-1 block w-full rounded-control border border-line bg-surface px-3 py-2 focus:border-primary-600 focus:outline-none focus:ring-2 focus:ring-primary-600/30';
@endphp
<x-layouts.public :title="__('membership.status.title')" :description="__('membership.status.description')">
    <div class="mx-auto max-w-page px-4 py-10 sm:px-6">
        <div class="max-w-2xl">
            <h1 class="heading-1">{{ __('membership.status.title') }}</h1>
            <p class="mt-2 text-lg text-ink-muted">{{ __('membership.status.description') }}</p>

            <form method="post" action="{{ route('membership.status') }}" class="mt-8 grid gap-4 sm:grid-cols-2" novalidate>
                @csrf
                <div>
                    <label for="reference" class="font-medium">{{ __('membership.reference') }}</label>
                    <input id="reference" name="reference" type="text" value="{{ old('reference', request('reference')) }}" required maxlength="20" autocomplete="off" dir="ltr" class="{{ $field }}">
                </div>
                <div>
                    <label for="phone" class="font-medium">{{ __('membership.status.contact') }}</label>
                    <input id="phone" name="phone" type="text" required maxlength="190" autocomplete="tel" dir="ltr" class="{{ $field }}">
                </div>
                <div class="sm:col-span-2">
                    <button type="submit" class="inline-flex items-center rounded-control bg-primary-600 px-5 py-2.5 font-semibold text-white hover:bg-primary-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600">{{ __('membership.status.check') }}</button>
                </div>
            </form>

            @if ($searched)
                <div role="status" class="mt-8 rounded-card border border-line bg-surface p-5">
                    @if ($application === null)
                        <p>{{ __('membership.status.not_found') }}</p>
                    @else
                        <p class="text-sm text-ink-muted" dir="ltr">{{ $application->reference }}</p>
                        <p class="mt-1 heading-3">{{ $application->status->label() }}</p>
                        <p class="mt-2 text-ink-muted">{{ __("membership.status.explain.{$application->status->value}") }}</p>
                        @if ($application->submitted_at)
                            <p class="mt-2 text-sm">{{ __('membership.status.sent_on') }} <x-date :date="$application->submitted_at" /></p>
                        @endif
                        @if ($application->status === ApplicationStatus::Rejected && filled($application->reason))
                            <p class="mt-2">{{ __('membership.status.reason', ['reason' => $application->reason]) }}</p>
                        @endif
                    @endif
                </div>
            @endif

            <p class="mt-10 text-sm text-ink-muted"><a href="{{ route('membership.apply') }}" class="text-link underline-offset-4 hover:underline">{{ __('membership.apply.link') }}</a></p>
        </div>
    </div>
</x-layouts.public>
