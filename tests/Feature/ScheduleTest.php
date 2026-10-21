<?php

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

it('starts every scheduled task at most once per due minute, however many scheduler runs overlap', function () {
    $events = collect(app(Schedule::class)->events());

    expect($events)->not->toBeEmpty()
        ->and($events->reject(fn (Event $event): bool => $event->onOneServer)->map(fn (Event $event): string => (string) ($event->description ?? $event->command))->values()->all())
        ->toBe([]);
});
