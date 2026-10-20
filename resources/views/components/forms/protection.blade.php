@props(['turnstileKey' => null])
@use('App\Support\SpamGuard')
@use('App\Support\Turnstile')
{{-- Robots fill in every field; people never see this one (PLAN.md §11). --}}
<div class="hidden" aria-hidden="true">
    <label for="{{ SpamGuard::HONEYPOT }}">{{ __('forms.honeypot') }}</label>
    <input type="text" id="{{ SpamGuard::HONEYPOT }}" name="{{ SpamGuard::HONEYPOT }}" value="" tabindex="-1" autocomplete="off">
</div>
<input type="hidden" name="{{ SpamGuard::STARTED }}" value="{{ SpamGuard::stamp() }}">
@if ($turnstileKey)
    <div class="cf-turnstile" data-sitekey="{{ $turnstileKey }}" data-language="auto" data-theme="auto"></div>
    <script src="{{ Turnstile::SCRIPT_URL }}" async defer nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}"></script>
@endif
