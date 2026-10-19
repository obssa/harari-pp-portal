@if ($changes === [])
    <p style="opacity: .75">{{ __('admin.revisions.no_changes') }}</p>
@else
    <p style="margin-bottom: 1rem; font-size: .875rem; opacity: .75">{{ __('admin.revisions.legend') }}</p>
    <div style="display: grid; gap: 1.25rem">
        @foreach ($changes as $change)
            <section>
                <h3 style="font-weight: 600; margin-bottom: .5rem">{{ $change['label'] }}</h3>
                <div style="display: grid; gap: .25rem; font-size: .875rem">
                    @foreach ($change['lines'] as $line)
                        @if ($line['type'] === 'removed')
                            <del style="display: block; padding: .25rem .5rem; border-radius: .25rem; background: rgb(254 226 226); color: rgb(127 29 29); text-decoration: line-through">{{ $line['text'] }}</del>
                        @elseif ($line['type'] === 'added')
                            <ins style="display: block; padding: .25rem .5rem; border-radius: .25rem; background: rgb(220 252 231); color: rgb(20 83 45); text-decoration: none">{{ $line['text'] }}</ins>
                        @else
                            <p style="padding: .25rem .5rem; opacity: .8">{{ $line['text'] }}</p>
                        @endif
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
@endif
