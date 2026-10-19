<?php

use App\Domain\Translation\Models\UiTranslation;
use Spatie\Activitylog\Models\Activity;

function freshTranslator(): void
{
    app()->forgetInstance('translator');
}

it('uses a string edited in the admin over the shipped file', function () {
    UiTranslation::query()->create(['key' => 'site.office', 'text' => ['am' => 'የተስተካከለ ጽሑፍ']]);
    freshTranslator();

    expect(__('site.office', [], 'am'))->toBe('የተስተካከለ ጽሑፍ')
        ->and(__('site.office', [], 'en'))->toBe('Harari Regional Branch Office');
});

it('shows a Harari string as soon as a translator saves it', function () {
    UiTranslation::query()->create(['key' => 'site.office', 'text' => ['har' => 'Harari text']]);
    freshTranslator();

    expect(__('site.office', [], 'har'))->toBe('Harari text');
});

it('ignores empty edits and never borrows another language for a row', function () {
    UiTranslation::query()->create(['key' => 'site.office', 'text' => ['am' => '', 'en' => 'Edited English']]);
    freshTranslator();

    expect(__('site.office', [], 'am'))->toBe('የሐረሪ ክልል ቅርንጫፍ ጽሕፈት ቤት')
        ->and(__('site.office', [], 'om'))->toBe('Waajjira Damee Naannoo Hararii');
});

it('refreshes the cached strings when a translation changes', function () {
    $row = UiTranslation::query()->create(['key' => 'site.office', 'text' => ['am' => 'First']]);
    freshTranslator();
    expect(__('site.office', [], 'am'))->toBe('First');

    $row->update(['text' => ['am' => 'Second']]);
    freshTranslator();
    expect(__('site.office', [], 'am'))->toBe('Second');

    $row->update(['text' => []]);
    freshTranslator();
    expect(__('site.office', [], 'am'))->toBe('የሐረሪ ክልል ቅርንጫፍ ጽሕፈት ቤት');
});

it('audits translation edits', function () {
    $row = UiTranslation::query()->create(['key' => 'site.office', 'text' => ['har' => 'One']]);
    $row->update(['text' => ['har' => 'Two']]);

    $entry = Activity::query()->where('log_name', 'translations')->where('event', 'updated')->latest('id')->firstOrFail();
    expect($entry->properties['old']['text'])->toBe(['har' => 'One'])
        ->and($entry->properties['attributes']['text'])->toBe(['har' => 'Two']);
});
