<?php

use App\Domain\Access\Enums\SystemRole;
use App\Domain\Api\Actions\IssueApiKey;
use App\Domain\Api\Enums\ApiScope;
use App\Domain\Content\Actions\SavePost;
use App\Domain\Content\Models\Post;
use App\Domain\Content\Workflow\ContentWorkflow;
use App\Domain\Content\Workflow\Transition;
use App\Domain\Documents\Actions\SaveDocument;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Documents\Models\Document;
use App\Domain\Events\Actions\SaveEvent;
use App\Domain\Events\Models\Event;
use App\Domain\Organisation\Actions\SaveLeader;
use App\Domain\Organisation\Actions\SaveOffice;
use App\Domain\Organisation\Models\Leader;
use App\Domain\Organisation\Models\Office;
use App\Domain\Projects\Actions\SaveProject;
use App\Domain\Projects\Models\Project;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Adds `$count` published records of one kind, each linked to an office
 * where the kind has one, so a query per record would show.
 */
function budgetRecords(string $kind, int $count, int $from = 0): void
{
    $publisher = staff(SystemRole::Publisher);
    $office = Office::query()->first() ?? app(SaveOffice::class)->handle(new Office, ['type' => 'woreda', 'translations' => ['en' => ['name' => 'Sample budget office', 'published' => true]]], $publisher);

    for ($i = $from; $i < $from + $count; $i++) {
        match ($kind) {
            'posts' => (function () use ($publisher, $i): void {
                $post = app(SavePost::class)->handle(new Post, ['type' => 'news', 'translations' => ['en' => ['title' => "Sample budget news {$i}", 'excerpt' => 'Sample']]], $publisher);
                app(ContentWorkflow::class)->apply($post, 'en', Transition::Publish, $publisher);
            })(),
            'events' => app(SaveEvent::class)->handle(new Event, [
                'starts_at' => CarbonImmutable::parse('+'.($i + 2).' days 09:00')->toDateTimeString(), 'office_id' => $office->id,
                'translations' => ['en' => ['title' => "Sample budget event {$i}", 'venue' => 'Sample hall', 'published' => true]],
            ], $publisher),
            'documents' => app(SaveDocument::class)->handle(new Document, [
                'type' => DocumentType::Report->value, 'year' => 2025, 'office_id' => $office->id,
                'translations' => ['en' => ['title' => "Sample budget report {$i}", 'published' => true]],
            ], $publisher),
            'leaders' => app(SaveLeader::class)->handle(new Leader, [
                'body' => 'executive_committee', 'office_id' => $office->id, 'rank' => $i,
                'translations' => ['en' => ['name' => "Sample Budget Leader {$i}", 'published' => true]],
            ], $publisher),
            'offices' => app(SaveOffice::class)->handle(new Office, ['type' => 'kebele', 'parent_id' => $office->id, 'translations' => ['en' => ['name' => "Sample budget kebele {$i}", 'published' => true]]], $publisher),
            'projects' => app(SaveProject::class)->handle(new Project, ['status' => 'ongoing', 'office_id' => $office->id, 'translations' => ['en' => ['title' => "Sample budget project {$i}", 'published' => true]]], $publisher),
        };
    }
}

/** Queries one request makes. */
function queriesFor(object $test, string $url, array $headers = []): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $test->get($url, $headers)->assertOk();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

it('reads a listing with the same number of queries for two records or eight', function (string $kind, string $url) {
    budgetRecords($kind, 2);
    queriesFor($this, $url);
    $few = queriesFor($this, $url);

    budgetRecords($kind, 6, from: 2);
    $many = queriesFor($this, $url);

    expect($many)->toBeLessThanOrEqual($few);
})->with([
    'news' => ['posts', '/en/news'],
    'events' => ['events', '/en/events'],
    'documents' => ['documents', '/en/documents'],
    'leaders' => ['leaders', '/en/leaders'],
    'offices' => ['offices', '/en/offices'],
    'projects' => ['projects', '/en/projects'],
]);

it('answers the API listings with the same number of queries for two records or eight', function (string $kind, string $url, ApiScope $scope) {
    [, $key] = app(IssueApiKey::class)->handle(['name' => 'Sample budget app (test)', 'scopes' => [$scope->value], 'rate_per_minute' => 600], staff(SystemRole::SuperAdmin));
    $headers = ['X-Api-Key' => $key, 'Accept' => 'application/json'];

    budgetRecords($kind, 2);
    queriesFor($this, $url, $headers);
    $few = queriesFor($this, $url, $headers);

    budgetRecords($kind, 6, from: 2);
    $many = queriesFor($this, $url, $headers);

    expect($many)->toBeLessThanOrEqual($few);
})->with([
    'posts' => ['posts', '/api/v1/posts?lang=en', ApiScope::Content],
    'events' => ['events', '/api/v1/events?lang=en', ApiScope::Events],
    'documents' => ['documents', '/api/v1/documents?lang=en', ApiScope::Documents],
    'leaders' => ['leaders', '/api/v1/leaders?lang=en', ApiScope::Directory],
    'offices' => ['offices', '/api/v1/offices?lang=en', ApiScope::Directory],
    'projects' => ['projects', '/api/v1/projects?lang=en', ApiScope::Projects],
]);
