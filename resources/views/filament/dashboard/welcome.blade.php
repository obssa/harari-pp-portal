{{-- ADR-0064: the dashboard opens on night blue with today's date in both calendars. --}}
<header class="hpp-welcome">
    @include('pages.home._plate', ['class' => 'hpp-welcome-plate'])
    <div class="hpp-welcome-inner">
        <div>
            <p class="hpp-welcome-kicker">{{ __('dashboard.welcome.kicker') }}</p>
            <h1 class="hpp-welcome-title">{{ $name !== '' ? __('dashboard.welcome.greeting', ['name' => $name]) : __('dashboard.welcome.greeting_plain') }}</h1>
            <p class="hpp-welcome-text">{{ __('dashboard.welcome.text') }}</p>
            <p class="hpp-welcome-date">
                <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>
                <x-date :date="now()" />
            </p>
        </div>
        <div class="hpp-welcome-actions">
            <a href="{{ route('home', ['locale' => \App\Domain\Translation\Support\Locales::isSupported(app()->getLocale()) ? app()->getLocale() : \App\Domain\Translation\Support\Locales::default()]) }}" target="_blank" rel="noopener" class="hpp-welcome-site">
                {{ __('dashboard.welcome.view_site') }}
                <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M7 17 17 7M9 7h8v8"/></svg>
            </a>
            @if ($actions !== [])
                <x-filament::actions :actions="$actions" />
            @endif
        </div>
    </div>
</header>
