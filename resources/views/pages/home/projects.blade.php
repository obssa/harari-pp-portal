@use('App\Domain\Organisation\Queries\PublicDirectory')
<section aria-labelledby="home-projects" class="mx-auto max-w-page px-4 py-12 sm:px-6">
    <div class="flex flex-wrap items-baseline justify-between gap-2">
        <h2 id="home-projects" class="heading-2">{{ $section->heading() }}</h2>
        <a href="{{ route('projects.index') }}" class="font-bold text-link underline-offset-4 hover:underline">{{ __('projects.all_projects') }}</a>
    </div>
    <ul class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($data as $project)
            @php $shown = PublicDirectory::shown($project, app()->getLocale()); @endphp
            @continue($shown === null)
            <li class="flex"><x-project-card :project="$project" :translation="$shown" :heading-level="3" class="w-full" /></li>
        @endforeach
    </ul>
</section>
