@use('App\Domain\Organisation\Queries\PublicDirectory')
@php
    $office = $node['office'];
    $shown = PublicDirectory::shown($office, app()->getLocale());
    $head = $office->head !== null ? PublicDirectory::shown($office->head, app()->getLocale()) : null;
@endphp
@if ($shown)
    <li lang="{{ $shown->locale }}">
        @if ($node['children'] !== [])
            <details @if ($level === 1) open @endif class="rounded-card border border-line bg-surface">
                <summary class="cursor-pointer px-4 py-3">
                    @include('pages.partials.structure-label')
                </summary>
                <ul class="space-y-2 border-t border-line py-3 pe-4 ps-6">
                    @foreach ($node['children'] as $child)
                        @include('pages.partials.structure-node', ['node' => $child, 'level' => $level + 1])
                    @endforeach
                </ul>
            </details>
        @else
            <div class="rounded-card border border-line bg-surface px-4 py-3">
                @include('pages.partials.structure-label')
            </div>
        @endif
    </li>
@endif
