<?php

use App\Domain\Access\Enums\SystemRole;
use App\Domain\Content\Actions\SavePost;
use App\Domain\Content\Models\Post;
use App\Domain\Forms\Models\FormSubmission;

/*
| Browser smoke tests (PLAN.md §13, ADR-0042): the paths people take most,
| clicked through in a real browser.
*/

require_once __DIR__.'/BrowserSupport.php';

beforeEach(function () {
    $this->withVite();
    browserContent();
});

it('switches language and keeps the page', function () {
    visit('/en/news')
        ->assertSee('Sample browser story')
        ->click('አማርኛ')
        ->assertPathIs('/am/news')
        ->assertSee('የናሙና የአሳሽ ዜና');
});

it('finds a story through the search form', function () {
    visit('/en/search')
        ->fill('#search-page-q', 'browser')
        ->click('form[role="search"] button[type="submit"]')
        ->assertQueryStringHas('q', 'browser')
        ->assertSee('Sample browser story');
});

it('sends a message through the contact form', function () {
    visit('/en/contact')
        ->fill('name', 'Sample Visitor')
        ->fill('email', 'visitor@example.org')
        ->fill('#q-msgfield', 'A sample message from the browser test.')
        ->wait(4)
        ->press(__('forms.send'))
        ->assertSee('Sample thanks for writing.')
        ->assertNoJavaScriptErrors();

    expect(FormSubmission::query()->count())->toBe(1);
});

it('publishes a post from the admin and shows it on the site', function () {
    $publisher = staff(SystemRole::Publisher, mfa: false, attributes: ['locale' => 'en']);
    $post = app(SavePost::class)->handle(new Post, ['type' => 'news', 'translations' => [
        'en' => ['title' => 'Sample story to publish', 'summary' => 'Published by the browser test.'],
    ]], $publisher);

    $this->actingAs($publisher);
    visit("/admin/posts/{$post->id}/edit")
        ->assertSee(__('content.transition.publish'))
        ->click(__('content.transition.publish'))
        ->click(__('filament-actions::modal.actions.confirm.label'))
        ->waitForText(__('content.workflow.done', ['transition' => __('content.transition.publish')]))
        ->assertSee(__('content.workflow.done', ['transition' => __('content.transition.publish')]));

    visit('/en/news/sample-story-to-publish')->assertSee('Sample story to publish');
});

it('shows each calendar its own months, limits the days and keeps letters out of the Fayda number (ADR-0076)', function () {
    $page = visit('/en/membership/apply');
    $visible = fn (string $id): string => '(() => document.getElementById('.json_encode($id).').offsetParent !== null)()';
    $days = '(() => [...document.getElementById("birth_day").options].filter((o) => o.value && !o.disabled).length)()';
    $choose = fn (string $id, string $value): string => '(() => { const e = document.getElementById('.json_encode($id).'); e.value = '.json_encode($value).'; e.dispatchEvent(new Event("change", {bubbles: true})); })()';

    // English opens on the Gregorian calendar: twelve months, no Pagume.
    expect($page->script($visible('birth_month_gregorian')))->toBeTrue()
        ->and($page->script($visible('birth_month_ethiopian')))->toBeFalse();
    $page->fill('#birth_year', '1995');
    $page->script($choose('birth_month_gregorian', '2'));
    expect($page->script($days))->toBe(28);

    $page->script('document.getElementById("birth-calendar-ethiopian").click()');
    expect($page->script($visible('birth_month_ethiopian')))->toBeTrue()
        ->and($page->script($visible('birth_month_gregorian')))->toBeFalse();
    $page->fill('#birth_year', '1979');
    $page->script($choose('birth_month_ethiopian', '13'));
    expect($page->script($days))->toBe(6);
    $page->fill('#birth_year', '1980');
    $page->script($choose('birth_month_ethiopian', '13'));
    expect($page->script($days))->toBe(5);

    $page->fill('#id_number', '12ab34 56');
    expect($page->script('document.getElementById("id_number").value'))->toBe('1234 56');
    $page->assertNoJavaScriptErrors();
});

it('opens the menu, search and phone menu panels just under the header, on screen', function () {
    $panel = fn (string $details): string => '(() => { const d = document.querySelector('.json_encode($details).'); const r = d.querySelector(":scope > div, :scope > nav").getBoundingClientRect(); return JSON.stringify([Math.round(r.top), Math.round(r.height), innerHeight]); })()';
    $open = fn (string $details): string => 'document.querySelector('.json_encode($details.' > summary').').click()';

    $desktop = visit('/en/news')->resize(1440, 900);
    foreach (['header > div:last-child nav details', 'header details.group\/search'] as $details) {
        $desktop->script($open($details));
        [$top, $height, $screen] = json_decode((string) $desktop->script($panel($details)), true);
        expect($top)->toBeGreaterThan(60)->toBeLessThan(300)
            ->and($height)->toBeGreaterThan(60)
            ->and($top + 60)->toBeLessThan($screen);
    }

    $phone = visit('/en/news')->resize(390, 844);
    $phone->script($open('header details.group\/menu'));
    [$top, $height] = json_decode((string) $phone->script($panel('header details.group\/menu')), true);
    expect($top)->toBeGreaterThan(40)->toBeLessThan(200)->and($height)->toBeGreaterThan(200);
});
