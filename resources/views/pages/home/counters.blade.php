<section aria-labelledby="home-counters" class="bg-primary-700 text-white dark:bg-primary-900">
    <div class="mx-auto max-w-page px-4 py-12 sm:px-6">
        <h2 id="home-counters" class="heading-2">{{ $section->heading() }}</h2>
        <dl class="mt-8 grid grid-cols-2 gap-6 lg:grid-cols-4">
            @foreach ($data as $item)
                <div class="flex flex-col-reverse gap-1">
                    <dt class="text-sm text-primary-100">{{ $item['label'] }}</dt>
                    <dd class="text-3xl font-bold tabular-nums" dir="ltr">{{ $item['value'] }}</dd>
                </div>
            @endforeach
        </dl>
    </div>
</section>
