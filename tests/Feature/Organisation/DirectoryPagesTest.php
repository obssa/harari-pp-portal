<?php

use App\Domain\Access\Enums\SystemRole;
use App\Domain\Content\Actions\SavePost;
use App\Domain\Content\Models\Post;
use App\Domain\Content\Workflow\ContentWorkflow;
use App\Domain\Content\Workflow\Transition;
use App\Domain\Organisation\Actions\SaveLeader;
use App\Domain\Organisation\Actions\SaveLeague;
use App\Domain\Organisation\Actions\SaveOffice;
use App\Domain\Organisation\Models\Leader;
use App\Domain\Organisation\Models\League;
use App\Domain\Organisation\Models\Office;

/**
 * @param  array<string, array<string, mixed>>  $translations
 * @param  array<string, mixed>  $data
 */
function directoryOffice(array $translations, string $type = 'woreda', array $data = []): Office
{
    return app(SaveOffice::class)->handle(new Office, ['type' => $type, 'translations' => $translations, ...$data], staff(SystemRole::Publisher));
}

/**
 * @param  array<string, array<string, mixed>>  $translations
 * @param  array<string, mixed>  $data
 */
function directoryLeader(array $translations, string $body = 'executive_committee', array $data = []): Leader
{
    return app(SaveLeader::class)->handle(new Leader, ['body' => $body, 'translations' => $translations, ...$data], staff(SystemRole::Publisher));
}

it('lists published leaders by body and hides drafts', function () {
    directoryLeader(['en' => ['name' => 'Sample Chair', 'position' => 'Sample head of the branch', 'published' => true]], data: ['rank' => 1]);
    directoryLeader(['en' => ['name' => 'Sample Member', 'published' => true]], 'central_committee');
    directoryLeader(['en' => ['name' => 'Sample Draft Person']]);

    $this->get('/en/leaders')
        ->assertOk()
        ->assertSeeInOrder([__('organisation.leader_body.executive_committee', [], 'en'), 'Sample Chair', __('organisation.leader_body.central_committee', [], 'en'), 'Sample Member'])
        ->assertDontSee('Sample Draft Person');

    $this->get('/am/leaders')->assertOk()->assertSee(__('directory.empty', [], 'am'));
});

it('shows a leader profile with office, term, socials, posts and person data', function () {
    $office = directoryOffice(['en' => ['name' => 'Sample Woreda Office', 'published' => true]]);
    $leader = directoryLeader(['en' => ['name' => 'Sample Speaker', 'position' => 'Sample role', 'summary' => 'Sample introduction.', 'published' => true]], 'office_heads', [
        'office_id' => $office->id,
        'term_start' => '2024-07-01',
        'socials' => ['telegram' => 'https://t.me/sample'],
        'email' => 'speaker@example.org',
    ]);
    $publisher = staff(SystemRole::Publisher);
    $speech = app(SavePost::class)->handle(new Post, [
        'type' => 'speech',
        'leaders' => [$leader->id],
        'translations' => ['en' => ['title' => 'Sample speech on the budget']],
    ], $publisher);
    app(ContentWorkflow::class)->apply($speech, 'en', Transition::Publish, $publisher);

    $html = (string) $this->get('/en/leaders/sample-speaker')
        ->assertOk()
        ->assertSee('Sample role')
        ->assertSee('/en/offices/sample-woreda-office')
        ->assertSee('https://t.me/sample')
        ->assertSee('mailto:speaker@example.org', escape: false)
        ->assertSee(__('leaders.present', [], 'en'))
        ->getContent();

    expect($html)->toContain('"@type":"Person"')
        ->toContain('Sample speech on the budget');

    $this->get('/am/leaders/sample-speaker')->assertNotFound();
});

it('lists offices with type filter and search, and shows an office page with map, head and sub-offices', function () {
    $head = directoryLeader(['en' => ['name' => 'Sample Office Head', 'published' => true]], 'office_heads');
    $branch = directoryOffice(['en' => ['name' => 'Sample Branch Office', 'address' => 'Sample street', 'hours' => 'Mon to Fri', 'published' => true]], 'branch', [
        'head_leader_id' => $head->id,
        'latitude' => '9.3125',
        'longitude' => '42.1194',
        'phone' => '+251 25 000 0000',
    ]);
    directoryOffice(['en' => ['name' => 'Sample Kebele Office', 'published' => true]], 'kebele', ['parent_id' => $branch->id]);
    directoryOffice(['en' => ['name' => 'Sample Unpublished Office']], 'sector');

    $this->get('/en/offices')->assertOk()->assertSee('Sample Branch Office')->assertSee('Sample Kebele Office')->assertDontSee('Sample Unpublished Office');
    $this->get('/en/offices?type=kebele')->assertOk()->assertDontSee('Sample Branch Office')->assertSee('Sample Kebele Office');
    $this->get('/en/offices?q=branch')->assertOk()->assertSee('Sample Branch Office')->assertDontSee('Sample Kebele Office');
    $this->get('/en/offices?q=%25')->assertOk()->assertSee(__('directory.empty', [], 'en'));

    $this->get('/en/offices/sample-branch-office')
        ->assertOk()
        ->assertSee('Sample Office Head')
        ->assertSee('Sample street')
        ->assertSee('tel:+251250000000', escape: false)
        ->assertSee('https://www.openstreetmap.org/export/embed.html', escape: false)
        ->assertSee('Sample Kebele Office')
        ->assertHeader('Content-Security-Policy');

    expect((string) $this->get('/en/offices/sample-branch-office')->headers->get('Content-Security-Policy'))
        ->toContain('https://www.openstreetmap.org');
});

it('shows leagues with their chair and leadership', function () {
    $chair = directoryLeader(['en' => ['name' => 'Sample League Chair', 'published' => true]], 'leagues');
    $league = app(SaveLeague::class)->handle(new League, [
        'head_leader_id' => $chair->id,
        'translations' => ['en' => ['name' => 'Sample Youth League', 'summary' => 'Sample summary', 'published' => true]],
    ], staff(SystemRole::Publisher));
    directoryLeader(['en' => ['name' => 'Sample League Secretary', 'published' => true]], 'leagues', ['league_id' => $league->id]);

    $this->get('/en/leagues')->assertOk()->assertSee('Sample Youth League')->assertSee('Sample summary');
    $this->get('/en/leagues/sample-youth-league')
        ->assertOk()
        ->assertSee('Sample League Chair')
        ->assertSee('Sample League Secretary');
});

it('builds the organisational structure from the office tree', function () {
    $head = directoryLeader(['en' => ['name' => 'Sample Structure Head', 'published' => true]], 'office_heads');
    $branch = directoryOffice(['en' => ['name' => 'Sample Root Office', 'published' => true]], 'branch', ['head_leader_id' => $head->id]);
    $woreda = directoryOffice(['en' => ['name' => 'Sample Child Woreda', 'published' => true]], 'woreda', ['parent_id' => $branch->id]);
    directoryOffice(['en' => ['name' => 'Sample Grandchild Kebele', 'published' => true]], 'kebele', ['parent_id' => $woreda->id]);

    $this->get('/en/structure')
        ->assertOk()
        ->assertSeeInOrder(['Sample Root Office', __('structure.headed_by', ['name' => 'Sample Structure Head'], 'en'), 'Sample Child Woreda', 'Sample Grandchild Kebele'])
        ->assertSee('<details', escape: false);
});

it('shows Amharic directory entries to Harari readers and links each language', function () {
    directoryLeader([
        'am' => ['name' => 'ናሙና መሪ', 'published' => true],
        'en' => ['name' => 'Sample Leader', 'published' => true],
    ]);

    $this->get('/har/leaders')->assertOk()->assertSee('ናሙና መሪ')->assertSee('lang="am"', escape: false);
    $this->get('/en/leaders/sample-leader')
        ->assertOk()
        ->assertSee('hreflang="am"', escape: false)
        ->assertSee(url('/om/leaders'), escape: false);
});

it('lists the directory in the sitemaps and keeps pages off its addresses', function () {
    directoryOffice(['en' => ['name' => 'Sample Mapped Office', 'published' => true]]);
    directoryLeader(['en' => ['name' => 'Sample Listed Leader', 'published' => true]]);

    $this->get('/sitemaps/en.xml')
        ->assertOk()
        ->assertSee(url('/en/offices/sample-mapped-office'), escape: false)
        ->assertSee(url('/en/leaders/sample-listed-leader'), escape: false)
        ->assertSee('<loc>'.url('/en/structure').'</loc>', escape: false);
});
