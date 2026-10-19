@props(['tone' => 'neutral'])
<span {{ $attributes->class([
    'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset',
    'bg-primary-50 text-primary-800 ring-primary-200 dark:bg-primary-950 dark:text-primary-200 dark:ring-primary-800' => $tone === 'primary',
    'bg-accent-50 text-accent-800 ring-accent-200 dark:bg-accent-950 dark:text-accent-200 dark:ring-accent-800' => $tone === 'accent',
    'bg-surface-muted text-ink-muted ring-line' => $tone === 'neutral',
    'bg-success-50 text-success-800 ring-success-600/30' => $tone === 'success',
    'bg-warning-50 text-warning-800 ring-warning-600/30' => $tone === 'warning',
    'bg-danger-50 text-danger-800 ring-danger-600/30' => $tone === 'danger',
]) }}>{{ $slot }}</span>
