@props(['phone' => null, 'email' => null, 'address' => null, 'hours' => null, 'socials' => []])
@if (filled($phone) || filled($email) || filled($address) || filled($hours) || $socials !== [])
    <dl {{ $attributes->class('space-y-3 text-sm') }}>
        @if (filled($address))
            <div>
                <dt class="font-semibold">{{ __('directory.address') }}</dt>
                <dd class="mt-0.5 whitespace-pre-line text-ink-muted">{{ $address }}</dd>
            </div>
        @endif
        @if (filled($hours))
            <div>
                <dt class="font-semibold">{{ __('directory.hours') }}</dt>
                <dd class="mt-0.5 text-ink-muted">{{ $hours }}</dd>
            </div>
        @endif
        @if (filled($phone))
            <div>
                <dt class="font-semibold">{{ __('directory.phone') }}</dt>
                <dd class="mt-0.5"><a href="tel:{{ preg_replace('/[^0-9+]/', '', (string) $phone) }}" class="text-link underline-offset-4 hover:underline" dir="ltr">{{ $phone }}</a></dd>
            </div>
        @endif
        @if (filled($email))
            <div>
                <dt class="font-semibold">{{ __('directory.email') }}</dt>
                <dd class="mt-0.5"><a href="mailto:{{ $email }}" class="text-link underline-offset-4 hover:underline">{{ $email }}</a></dd>
            </div>
        @endif
        @if ($socials !== [])
            <div>
                <dt class="font-semibold">{{ __('directory.follow') }}</dt>
                <dd class="mt-1 flex flex-wrap gap-2">
                    @foreach ($socials as $network => $url)
                        <a href="{{ $url }}" rel="noopener me" class="inline-block rounded-control border border-line px-3 py-1 hover:bg-surface-muted">{{ __("organisation.social.{$network}") }}</a>
                    @endforeach
                </dd>
            </div>
        @endif
    </dl>
@endif
