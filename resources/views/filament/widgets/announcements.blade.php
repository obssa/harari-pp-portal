<x-filament-widgets::widget>
    @php $announcements = $this->announcements(); @endphp
    @if ($announcements->isNotEmpty())
        <x-filament::section :heading="$this::title()" icon="heroicon-o-megaphone">
            <ul class="hpp-stack">
                @foreach ($announcements as $announcement)
                    <li class="hpp-note">
                        <p class="hpp-note-head">
                            <x-filament::badge :color="$announcement->tone === 'warning' ? 'warning' : ($announcement->tone === 'success' ? 'success' : 'info')">{{ __("dashboard.tone.{$announcement->tone}") }}</x-filament::badge>
                            {{ $announcement->title }}
                        </p>
                        @if (filled($announcement->body))
                            <p class="hpp-note-body">{{ $announcement->body }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        </x-filament::section>
    @endif
</x-filament-widgets::widget>
