<div class="my-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    @foreach ($statistics as $statistic)
        <x-statistic-tile :statistic="$statistic" />
    @endforeach
</div>
