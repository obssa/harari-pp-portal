<?php

use App\Domain\Access\Enums\SystemRole;
use App\Domain\Content\Actions\SavePost;
use App\Domain\Content\Models\Post;
use App\Domain\Content\Workflow\ContentWorkflow;
use App\Domain\Content\Workflow\Transition;
use App\Domain\Events\Actions\SaveEvent;
use App\Domain\Events\Models\Event;
use App\Domain\Forms\Actions\SaveForm;
use App\Domain\Forms\Models\Form;
use App\Domain\Forms\Support\FormField;
use App\Domain\Organisation\Actions\SaveOffice;
use App\Domain\Organisation\Models\Office;
use Carbon\CarbonImmutable;

/*
| Sample content for the browser tests (PLAN.md §13: no real people): one
| published story, an event, an office and the contact form.
*/

function browserContent(): void
{
    $publisher = staff(SystemRole::Publisher, mfa: false);

    $post = app(SavePost::class)->handle(new Post, ['type' => 'news', 'translations' => [
        'en' => ['title' => 'Sample browser story', 'summary' => 'A sample story for the browser tests.'],
        'am' => ['title' => 'የናሙና የአሳሽ ዜና', 'summary' => 'ለአሳሽ ሙከራዎች የናሙና ዜና።'],
    ]], $publisher);
    foreach (['en', 'am'] as $locale) {
        app(ContentWorkflow::class)->apply($post, $locale, Transition::Publish, $publisher);
    }

    app(SaveEvent::class)->handle(new Event, [
        'starts_at' => CarbonImmutable::now()->addDays(5)->setTime(9, 0)->toDateTimeString(),
        'registration_enabled' => true,
        'translations' => ['en' => ['title' => 'Sample browser forum', 'venue' => 'Sample hall', 'published' => true]],
    ], $publisher);

    app(SaveOffice::class)->handle(new Office, ['type' => 'woreda', 'email' => 'office@example.org', 'phone' => '+251911000000',
        'translations' => ['en' => ['name' => 'Sample browser office', 'published' => true]]], $publisher);

    app(SaveForm::class)->handle(new Form, [
        'type' => 'contact',
        'on_contact_page' => true,
        'active' => true,
        'response_days' => 3,
        'translations' => ['en' => ['title' => 'Sample contact form', 'success_message' => 'Sample thanks for writing.', 'published' => true]],
        'fields' => [['key' => 'msgfield', 'type' => FormField::TEXTAREA, 'required' => true, 'label' => ['en' => 'Your message']]],
    ], $publisher);
}
