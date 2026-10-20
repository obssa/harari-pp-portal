@use('App\Domain\Translation\Support\Locales')
@php
    $media = $file?->file;
    $pdf = $media !== null && $media->mime_type === 'application/pdf';
    $otherLanguage = $file !== null && $file->locale !== $translation->locale;
@endphp
<x-layouts.public :title="$translation->title" :description="$translation->summary" :alternates="$alternates" :json-ld="$jsonLd">
    <article class="mx-auto max-w-page px-4 py-10 sm:px-6">
        <nav aria-label="{{ __('news.breadcrumb') }}" class="text-sm text-ink-muted">
            <ol class="flex flex-wrap gap-1">
                <li><a href="{{ route('home') }}" class="hover:underline">{{ __('nav.home') }}</a> /</li>
                <li><a href="{{ route($indexRoute) }}" class="hover:underline">{{ $document->type->isNotice() ? __('documents.notices_title') : __('documents.title') }}</a></li>
            </ol>
        </nav>

        <header class="mt-6 max-w-3xl">
            <p class="flex flex-wrap items-center gap-2 text-sm">
                <x-ui.badge>{{ $document->type->label() }}</x-ui.badge>
                @if ($document->deadline_at)
                    <x-ui.badge :tone="$document->isClosed() ? 'neutral' : 'warning'">{{ $document->isClosed() ? __('documents.closed') : __('documents.open') }}</x-ui.badge>
                @endif
            </p>
            <h1 class="heading-1 mt-3">{{ $translation->title }}</h1>
            @if ($translation->summary)
                <p class="mt-3 text-lg text-ink-muted">{{ $translation->summary }}</p>
            @endif
        </header>

        <div class="mt-10 grid gap-10 lg:grid-cols-3">
            <div class="lg:col-span-2">
                @if (trim($body) !== '')
                    <div class="prose-content max-w-prose">{!! $body !!}</div>
                @endif

                @if ($pdf)
                    <section aria-labelledby="document-viewer" class="mt-8">
                        <h2 id="document-viewer" class="sr-only">{{ __('documents.viewer') }}</h2>
                        <iframe src="{{ route('media.file', ['uuid' => $media->uuid, 'name' => $media->file_name]) }}#view=FitH" title="{{ __('documents.viewer_of', ['title' => $translation->title]) }}" loading="lazy"
                                class="h-[75vh] min-h-96 w-full rounded-card border border-line bg-surface-muted"></iframe>
                        <p class="mt-2 text-sm text-ink-muted">{{ __('documents.viewer_help') }}</p>
                    </section>
                @endif

                @if ($related->isNotEmpty())
                    <section aria-labelledby="document-related" class="mt-12">
                        <h2 id="document-related" class="heading-2">{{ __('documents.related') }}</h2>
                        <ul class="mt-4 grid gap-4 sm:grid-cols-2">
                            @foreach ($related as $item)
                                <li><x-document-row :document="$item['document']" :translation="$item['translation']" :heading-level="3" /></li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            </div>

            <aside class="space-y-6" aria-label="{{ __('documents.details') }}">
                @if ($media)
                    <div class="rounded-card border border-line bg-surface p-4">
                        <a href="{{ route('documents.download', ['slug' => $translation->slug]) }}" rel="nofollow"
                           class="inline-flex w-full justify-center rounded-control bg-primary-600 px-4 py-2.5 font-semibold text-white hover:bg-primary-700">{{ __('documents.download') }}</a>
                        <p class="mt-2 text-center text-sm text-ink-muted">
                            {{ strtoupper(pathinfo($media->file_name, PATHINFO_EXTENSION)) }} · {{ \Illuminate\Support\Number::fileSize($media->size, precision: 1) }}
                            · {{ __('documents.version', ['number' => $file->version]) }}
                        </p>
                        @if ($otherLanguage)
                            <p class="mt-2 text-center text-sm" lang="{{ $file->locale }}">{{ __('documents.file_in', ['language' => Locales::nativeName($file->locale)]) }}</p>
                        @endif
                    </div>
                @else
                    <p class="rounded-card border border-line bg-surface p-4 text-sm text-ink-muted">{{ __('documents.no_file') }}</p>
                @endif

                <dl class="space-y-3 text-sm">
                    @if ($document->deadline_at)
                        <div>
                            <dt class="font-semibold">{{ __('documents.deadline') }}</dt>
                            <dd class="mt-0.5 text-ink-muted"><x-date :date="$document->deadline_at" :time="true" /></dd>
                        </div>
                    @endif
                    @if ($document->issued_on)
                        <div>
                            <dt class="font-semibold">{{ __('documents.issued_on') }}</dt>
                            <dd class="mt-0.5 text-ink-muted"><x-date :date="$document->issued_on" /></dd>
                        </div>
                    @elseif ($document->year)
                        <div>
                            <dt class="font-semibold">{{ __('documents.filter_year') }}</dt>
                            <dd class="mt-0.5 text-ink-muted">{{ $document->year }}</dd>
                        </div>
                    @endif
                    @if ($office)
                        <div>
                            <dt class="font-semibold">{{ __('admin.leaders.field.office') }}</dt>
                            <dd class="mt-0.5"><a href="{{ route('offices.show', ['locale' => $office->locale, 'slug' => $office->slug]) }}" class="text-link hover:underline">{{ $office->getAttribute('name') }}</a></dd>
                        </div>
                    @endif
                    @if ($league)
                        <div>
                            <dt class="font-semibold">{{ __('admin.leaders.field.league') }}</dt>
                            <dd class="mt-0.5"><a href="{{ route('leagues.show', ['locale' => $league->locale, 'slug' => $league->slug]) }}" class="text-link hover:underline">{{ $league->getAttribute('name') }}</a></dd>
                        </div>
                    @endif
                    @if ($file?->version_note)
                        <div>
                            <dt class="font-semibold">{{ __('documents.whats_new') }}</dt>
                            <dd class="mt-0.5 text-ink-muted">{{ $file->version_note }}</dd>
                        </div>
                    @endif
                </dl>

                @if ($versions->isNotEmpty())
                    <section aria-labelledby="document-versions">
                        <h2 id="document-versions" class="font-semibold">{{ __('documents.earlier_versions') }}</h2>
                        <ul class="mt-2 space-y-2 text-sm">
                            @foreach ($versions as $version)
                                <li>
                                    <a href="{{ route('documents.download', ['slug' => $translation->slug, 'version' => $version->version]) }}" rel="nofollow" class="text-link underline-offset-4 hover:underline">{{ __('documents.version', ['number' => $version->version]) }}</a>
                                    <span class="text-ink-muted">· {{ __('documents.replaced_on') }} <x-date :date="$version->replaced_at" /></span>
                                    @if ($version->note)
                                        <span class="block text-ink-muted">{{ $version->note }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            </aside>
        </div>
    </article>
</x-layouts.public>
