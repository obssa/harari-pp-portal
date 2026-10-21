@props(['href', 'active' => false])
{{-- ADR-0062: filter chips; the chosen one is ink, the rest sit on the page's ground. --}}
<a href="{{ $href }}" @if ($active) aria-current="page" @endif
   {{ $attributes->class([
       'inline-flex items-center rounded-full border px-4 py-2 text-sm font-bold transition-colors duration-150',
       'border-ink-brand bg-ink-brand text-white dark:border-white dark:bg-white dark:text-ink-brand' => $active,
       'border-plaster-line bg-surface text-ink hover:border-ink-brand' => ! $active,
   ]) }}>{{ $slot }}</a>
