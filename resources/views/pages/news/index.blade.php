@use('App\Domain\Content\Enums\PostType')
@use('App\Domain\Content\Queries\PublicPosts')
@php($locale = app()->getLocale())
<x-layouts.public :title="$type ? $type->label() : __('news.title')" :description="__('news.description')">
    <x-page-hero :title="$type ? $type->label() : __('news.title')" :lead="__('news.description')" :crumbs="$type ? [[__('news.title'), route('news.index')]] : []">
        <x-slot:actions>
            <x-ui.button :href="route('gallery.index')" variant="secondary" size="sm">{{ __('gallery.title') }}</x-ui.button>
            <x-ui.button :href="route('press')" variant="secondary" size="sm">{{ __('press.title') }}</x-ui.button>
        </x-slot:actions>
        <nav aria-label="{{ __('news.filter_by_type') }}">
            <ul class="flex flex-wrap gap-2">
                <li><x-ui.chip :href="route('news.index')" :active="! $type">{{ __('news.all') }}</x-ui.chip></li>
                @foreach (PostType::cases() as $option)
                    <li><x-ui.chip :href="route('news.index', ['type' => $option->value])" :active="$type === $option">{{ $option->label() }}</x-ui.chip></li>
                @endforeach
            </ul>
        </nav>
    </x-page-hero>
    <div class="mx-auto max-w-page px-4 pt-2 pb-12 sm:px-6">

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
