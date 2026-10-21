{{-- The member portal's own menu (ADR-0049). --}}
@php
    $links = ['member.home' => 'members.portal.nav.home', 'member.card' => 'members.portal.nav.card', 'member.profile' => 'members.portal.nav.profile',
        'member.events' => 'members.portal.nav.events', 'member.documents' => 'members.portal.nav.documents'];
@endphp
<nav aria-label="{{ __('members.portal.nav.label') }}" class="mt-6 flex flex-wrap items-center gap-2 border-b border-line pb-4 print:hidden">
    @foreach ($links as $route => $label)
        <a href="{{ route($route) }}" @if (request()->routeIs($route)) aria-current="page" @endif
           @class(['rounded-full px-3 py-1 text-sm font-semibold', 'bg-primary-600 text-white' => request()->routeIs($route), 'text-link hover:bg-surface-muted' => ! request()->routeIs($route)])>{{ __($label) }}</a>
    @endforeach
    <form method="post" action="{{ route('member.sign-out') }}" class="ms-auto">
        @csrf
        <button type="submit" class="rounded-full px-3 py-1 text-sm font-semibold text-ink-muted hover:bg-surface-muted">{{ __('members.portal.sign_out') }}</button>
    </form>
</nav>
