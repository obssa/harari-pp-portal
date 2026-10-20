<?php

use App\Domain\Access\Enums\SystemRole;
use App\Domain\Documents\Models\Document;
use App\Domain\Events\Models\Event;
use App\Domain\Forms\Models\Form;
use App\Domain\Organisation\Queries\PublicDirectory;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Models\Statistic;
use App\Domain\Translation\Support\Locales;

/*
| Phase 3 gate (PLAN.md §13): with the labelled sample content in place,
| every page of section 4 that Phase 3 adds renders in the four languages.
*/

it('adds labelled sample content and every Phase 3 page renders in the four languages', function () {
    staff(SystemRole::SuperAdmin);
    $this->artisan('portal:sample-content')->assertSuccessful();

    expect(Event::query()->count())->toBe(2)
        ->and(Document::query()->count())->toBe(3)
        ->and(Project::query()->count())->toBe(2)
        ->and(Statistic::query()->count())->toBe(2)
        ->and(Form::query()->count())->toBe(1);

    $event = Event::query()->whereNotNull('ends_at')->firstOrFail();
    $document = Document::query()->where('type', 'programme')->firstOrFail();
    $project = Project::query()->where('status', 'ongoing')->firstOrFail();
    $form = Form::query()->firstOrFail();

    $urls = [];
    foreach (Locales::all() as $locale) {
        foreach (['events.index', 'documents.index', 'documents.notices', 'projects.index', 'projects.achievements', 'gallery.index',
            'videos.index', 'videos.live', 'contact', 'press', 'get-involved', 'tickets.lookup'] as $route) {
            $urls[] = route($route, ['locale' => $locale]);
        }
        $urls[] = route('events.index', ['locale' => $locale, 'month' => $event->starts_at->format('Y-m')]);
    }
    foreach ([[$event, 'events.show', 'events.index'], [$document, 'documents.show', 'documents.index'], [$project, 'projects.show', 'projects.index'], [$form, 'forms.show', 'contact']] as [$record, $show, $index]) {
        $urls = [...$urls, ...array_values(PublicDirectory::alternates($record, $show, $index))];
    }
    $translation = $event->translation('en');
    $urls[] = route('events.register', ['locale' => 'en', 'slug' => $translation?->getAttribute('slug')]);

    foreach (array_unique($urls) as $url) {
        $response = $this->get($url);
        expect($response->status())->toBe(200, "{$url} answered {$response->status()}");
    }

    $this->get(route('events.show', ['locale' => 'am', 'slug' => $event->translation('am')?->getAttribute('slug')]))->assertSee('ናሙና፦', escape: false);
    $this->get(route('projects.show', ['locale' => 'om', 'slug' => $project->translation('om')?->getAttribute('slug')]))->assertSee('Fakkeenya:', escape: false);
});

it('removes exactly the sample content again and is never run in production', function () {
    staff(SystemRole::SuperAdmin);
    $this->artisan('portal:sample-content')->assertSuccessful();
    // Running it again replaces the samples instead of adding more.
    $this->artisan('portal:sample-content')->assertSuccessful();
    expect(Event::query()->count())->toBe(2);

    $this->artisan('portal:sample-content --remove')->assertSuccessful();
    expect(Event::query()->count())->toBe(0)
        ->and(Document::query()->withTrashed()->count())->toBe(0)
        ->and(Project::query()->withTrashed()->count())->toBe(0)
        ->and(Statistic::query()->count())->toBe(0)
        ->and(Form::query()->withTrashed()->count())->toBe(0);

    app()->detectEnvironment(fn (): string => 'production');
    $this->artisan('portal:sample-content')->assertFailed();
});
