@use('App\Domain\Forms\Enums\SubmissionStatus')
@php
    $tone = match ($submission->status) {
        SubmissionStatus::Resolved => 'success',
        SubmissionStatus::New => 'warning',
        default => 'primary',
    };
@endphp
<x-layouts.public :title="__('forms.ticket_title')" :alternates="[]">
    <div class="mx-auto max-w-xl px-4 py-10 sm:px-6">
        @if (session('status'))
            <p role="status" class="mb-6 whitespace-pre-line rounded-card border border-primary-600/40 bg-primary-50 px-4 py-3 text-primary-800 dark:bg-primary-950 dark:text-primary-200">{{ session('status') }}</p>
        @endif

        <article class="rounded-card border border-line bg-surface p-6">
            <h1 class="heading-2">{{ __('forms.ticket_title') }}</h1>
            <dl class="mt-4 space-y-3 text-sm">
                <div>
                    <dt class="font-semibold">{{ __('forms.reference') }}</dt>
                    <dd class="mt-0.5 font-mono text-lg tracking-wider" dir="ltr">{{ $submission->reference }}</dd>
                </div>
                <div>
                    <dt class="font-semibold">{{ __('forms.form') }}</dt>
                    <dd class="mt-0.5 text-ink-muted">{{ $title }}</dd>
                </div>
                <div>
                    <dt class="font-semibold">{{ __('forms.status_label') }}</dt>
                    <dd class="mt-1"><x-ui.badge :tone="$tone">{{ $submission->status->label() }}</x-ui.badge></dd>
                </div>
                <div>
                    <dt class="font-semibold">{{ __('forms.sent_on') }}</dt>
                    <dd class="mt-0.5 text-ink-muted"><x-date :date="$submission->created_at" :time="true" /></dd>
                </div>
                @if ($submission->first_response_at)
                    <div>
                        <dt class="font-semibold">{{ __('forms.answered_on') }}</dt>
                        <dd class="mt-0.5 text-ink-muted"><x-date :date="$submission->first_response_at" :time="true" /></dd>
                    </div>
                @elseif ($submission->due_at && $submission->status->isOpen())
                    <div>
                        <dt class="font-semibold">{{ __('forms.answer_by') }}</dt>
                        <dd class="mt-0.5 text-ink-muted"><x-date :date="$submission->due_at" /></dd>
                    </div>
                @endif
            </dl>
        </article>
        <p class="mt-6 text-center text-sm text-ink-muted">{{ __('forms.ticket_keep') }}</p>
    </div>
</x-layouts.public>
