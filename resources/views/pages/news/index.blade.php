@use('App\Domain\Content\Enums\PostType')
@use('App\Domain\Content\Queries\PublicPosts')
@php($locale = app()->getLocale())
<x-layouts.public :title="$type ? $type->label() : __('news.title')" :description="__('news.description')">
    <div class="mx-auto max-w-page px-4 py-10 sm:px-6">
        <header class="max-w-prose">
            <h1 class="heading-1">{{ $type ? $type->label() : __('news.title') }}</h1>
            <p class="mt-2 text-ink-muted">{{ __('news.description') }}</p>
        </header>

        <nav aria-label="{{ __('news.filter_by_type') }}" class="mt-6">
            <ul class="flex flex-wrap gap-2 text-sm">
                <li>
                    <a href="{{ route('news.index') }}" @if (! $type) aria-current="page" @endif
                       @class(['inline-block rounded-full border px-3 py-1', 'border-primary-600 bg-primary-50 text-primary-800 dark:bg-primary-950 dark:text-primary-200' => ! $type, 'border-line hover:bg-surface-muted' => (bool) $type])>{{ __('news.all') }}</a>
                </li>
                @foreach (PostType::cases() as $option)
                    <li>
                        <a href="{{ route('news.index', ['type' => $option->value]) }}" @if ($type === $option) aria-current="page" @endif
                           @class(['inline-block rounded-full border px-3 py-1', 'border-primary-600 bg-primary-50 text-primary-800 dark:bg-primary-950 dark:text-primary-200' => $type === $option, 'border-line hover:bg-surface-muted' => $type !== $option])>{{ $option->label() }}</a>
                    </li>
                @endforeach
            </ul>
        </nav>

        @if ($posts->isEmpty())
            <p class="mt-10 rounded-card border border-line bg-surface px-5 py-8 text-center text-ink-muted">{{ __('news.empty') }}</p>
        @else
            <ul class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($posts as $post)
                    @php($shown = PublicPosts::shown($post, $locale))
                    @continue($shown === null)
                    <li lang="{{ $shown->locale }}">
                        <x-ui.card
                            :title="$shown->title"
                            :href="route('news.show', ['locale' => $shown->locale, 'slug' => $shown->slug])"
                            :media="$post->featuredImage"
                            :kicker="$shown->kicker"
                            :heading-level="2"
                        >
                            {{ $shown->excerpt }}
                            <x-slot:badges>
                                <x-ui.badge :tone="$post->type->tone()">{{ $post->type->label() }}</x-ui.badge>
                                @if ($shown->locale !== $locale)
                                    <x-ui.badge>{{ __('news.in_language', ['language' => \App\Domain\Translation\Support\Locales::nativeName($shown->locale)]) }}</x-ui.badge>
                                @endif
                            </x-slot:badges>
                            <x-slot:meta><x-date :date="$shown->published_at" /></x-slot:meta>
                        </x-ui.card>
                    </li>
                @endforeach
            </ul>

            <div class="mt-10">{{ $posts->links() }}</div>
        @endif

        <p class="mt-10 text-sm">
            <a href="{{ route('feed.rss') }}" class="text-link underline-offset-4 hover:underline">{{ __('news.feed') }}</a>
        </p>
    </div>
</x-layouts.public>
