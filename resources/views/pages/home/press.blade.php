@use('App\Domain\Content\Queries\PublicPosts')
<section aria-labelledby="home-press" class="mx-auto max-w-page px-4 py-8 sm:px-6">
    <div class="rounded-[1.25rem] bg-surface-muted p-6 sm:p-8">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 id="home-press" class="heading-2">{{ $section->heading() }}</h2>
            <a href="{{ route('news.index', ['type' => 'press_release']) }}" class="font-bold text-link underline-offset-4 hover:underline">{{ __('homepage.all_press') }}</a>
        </div>
        <ul class="mt-4 divide-y divide-plaster-line">
            @foreach ($data as $post)
                @php $shown = PublicPosts::shown($post, app()->getLocale()); @endphp
                @continue($shown === null)
                <li lang="{{ $shown->locale }}" class="flex flex-col gap-1 py-4 sm:flex-row sm:items-baseline sm:gap-4">
                    <span class="shrink-0 text-xs text-ink-muted"><x-date :date="$shown->published_at" /></span>
                    <a href="{{ route('news.show', ['locale' => $shown->locale, 'slug' => $shown->slug]) }}" class="font-semibold underline-offset-4 hover:underline">{{ $shown->title }}</a>
                    <x-ui.badge :tone="$post->type->tone()" class="sm:ms-auto">{{ $post->type->label() }}</x-ui.badge>
                </li>
            @endforeach
        </ul>
    </div>
</section>
