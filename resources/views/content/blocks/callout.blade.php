<aside role="note" @class([
    'my-6 rounded-card border-s-4 px-5 py-4',
    'border-primary-600 bg-primary-50 dark:bg-primary-950' => $tone === 'info',
    'border-warning-600 bg-warning-50 text-warning-800' => $tone === 'warning',
    'border-success-600 bg-success-50 text-success-800' => $tone === 'success',
])>
    <p class="m-0">{{ $text }}</p>
</aside>
