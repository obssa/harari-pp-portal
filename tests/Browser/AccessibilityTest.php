<?php

/*
| PLAN.md §11 (WCAG 2.2 AA), ADR-0042: axe checks the main public pages in a
| real browser with the built styles, so colour contrast is checked too.
| Serious and critical issues fail the build.
*/

require_once __DIR__.'/BrowserSupport.php';

beforeEach(function () {
    $this->withVite();
    browserContent();
});

it('has no serious accessibility issues on the main pages', function () {
    // One test for all pages: setting up the database is the slow part of a browser test.
    $pages = [
        '/en', '/am', '/en/news', '/en/news/sample-browser-story', '/am/news/yenamuna-yeasash-zena',
        '/en/events', '/en/events/sample-browser-forum', '/en/events/sample-browser-forum/register',
        '/en/offices', '/en/offices/sample-browser-office', '/en/documents', '/en/search?q=sample',
        '/en/contact', '/en/social', '/en/press', '/en/structure', '/en/get-involved', '/am/membership/apply', '/en/membership/status', '/om/volunteer',
    ];

    foreach ($pages as $path) {
        visit($path)
            ->assertNoJavaScriptErrors()
            ->assertNoAccessibilityIssues();
    }
});

it('can be used with the keyboard: the skip link leads to the content', function () {
    visit('/en')
        ->keys('html > body', 'Tab')
        ->keys('html > body', 'Enter')
        ->assertFragmentIs('content');
});
