<x-layouts.public :title="__('members.portal.nav.documents')">
    <div class="mx-auto max-w-page px-4 py-10 sm:px-6">
        <h1 class="heading-1">{{ __('members.portal.nav.documents') }}</h1>
        @include('pages.member.nav')
        <p class="mt-6 max-w-2xl text-ink-muted">{{ __('members.portal.documents_help') }}</p>
        @if ($documents->isEmpty())
            <p class="mt-6 text-ink-muted">{{ __('members.portal.no_documents') }}</p>
        @else
            <ul class="mt-6 max-w-3xl divide-y divide-line">
                @foreach ($documents as $document)
                    @php($translation = $document->translationOrAny($locale))
                    <li class="flex flex-wrap items-baseline justify-between gap-2 py-4">
                        <span>
                            <span class="block font-semibold">{{ $document->title($locale) }}</span>
                            @if ($document->issued_on)<span class="text-sm text-ink-muted"><x-date :date="$document->issued_on" /></span>@endif
                        </span>
                        @if ($translation?->getAttribute('locale') === $locale && $document->fileFor($locale))
                            <a href="{{ route('member.download', ['slug' => $translation->getAttribute('slug')]) }}" class="text-sm font-semibold text-link underline underline-offset-4">{{ __('members.portal.download') }}</a>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-layouts.public>
