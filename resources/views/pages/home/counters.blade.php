@php $accents = ['border-gold-500', 'border-sky-brand', 'border-accent-500', 'border-emerald-500']; @endphp
<section aria-labelledby="home-counters" class="relative overflow-hidden bg-primary-900 text-white">
    @include('pages.home._plate', ['class' => 'animate-spin-slower pointer-events-none absolute -end-24 -bottom-24 size-80 opacity-30'])
    <div class="relative mx-auto max-w-page px-4 py-16 sm:px-6">
        <h2 id="home-counters" class="heading-2 text-white">{{ $section->heading() }}</h2>
        <dl class="mt-8 grid grid-cols-2 gap-4 lg:grid-cols-4">
            @foreach ($data as $item)
                <div class="reveal flex flex-col-reverse gap-2 rounded-card border-t-4 bg-primary-800 p-6 transition hover:-translate-y-1 hover:bg-primary-700 {{ $accents[$loop->index % 4] }}">
                    <dt class="text-sm text-primary-100">{{ $item['label'] }}</dt>
                    <dd class="text-4xl font-extrabold tracking-tight tabular-nums" dir="ltr">{{ $item['value'] }}</dd>
                </div>
            @endforeach
        </dl>
    </div>
</section>
