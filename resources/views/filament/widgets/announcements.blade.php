<x-filament-widgets::widget>
    @php $announcements = $this->announcements(); @endphp
    @if ($announcements->isNotEmpty())
        <x-filament::section :heading="$this::title()" icon="heroicon-o-megaphone">
            <ul style="display: grid; gap: .75rem">
                @foreach ($announcements as $announcement)
                    <li style="padding: .75rem; border: 1px solid rgb(128 128 128 / .25); border-radius: .5rem">
                        <p style="display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; font-weight: 600">
                            <x-filament::badge :color="$announcement->tone === 'warning' ? 'warning' : ($announcement->tone === 'success' ? 'success' : 'info')">{{ __("dashboard.tone.{$announcement->tone}") }}</x-filament::badge>
                            {{ $announcement->title }}
                        </p>
                        @if (filled($announcement->body))
                            <p style="margin-top: .25rem; white-space: pre-line; font-size: .875rem; opacity: .7">{{ $announcement->body }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        </x-filament::section>
    @endif
</x-filament-widgets::widget>
