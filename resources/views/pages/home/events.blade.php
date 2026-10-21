@use('App\Domain\Organisation\Queries\PublicDirectory')
<section aria-labelledby="home-events" class="bg-surface-muted">
    <div class="mx-auto max-w-page px-4 py-16 sm:px-6">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 id="home-events" class="heading-2">{{ $section->heading() }}</h2>
            <a href="{{ route('events.index') }}" class="font-bold text-link underline-offset-4 hover:underline">{{ __('events.all') }}</a>
        </div>
        <ul class="mt-8 grid gap-4 lg:grid-cols-3">
            @foreach ($data as $event)
                @php $shown = PublicDirectory::shown($event, app()->getLocale()); @endphp
                @continue($shown === null)
                <li><x-event-row :event="$event" :translation="$shown" :heading-level="3" class="h-full" /></li>
            @endforeach
        </ul>
    </div>
</section>
