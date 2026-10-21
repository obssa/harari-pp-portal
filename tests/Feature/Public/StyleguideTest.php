<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Blade;

it('shows the styleguide outside production', function () {
    $this->get('/styleguide')
        ->assertOk()
        ->assertSee('Styleguide')
        ->assertSee('--color-primary-600', escape: false)
        ->assertSee('<html lang="en">', escape: false);
});

it('shows the styleguide in another language for typography checks', function () {
    $this->get('/styleguide?lang=am')
        ->assertOk()
        ->assertSee('<html lang="am">', escape: false)
        ->assertSee(__('holding.title', [], 'am'));

    $this->get('/styleguide?lang=fr')->assertSee('<html lang="en">', escape: false);
});

it('hides the styleguide in production', function () {
    app()->detectEnvironment(fn (): string => 'production');

    $this->get('/styleguide')->assertNotFound();
});

it('renders buttons as links or buttons with the chosen variant', function () {
    expect(Blade::render('<x-ui.button href="/en" variant="secondary">Go</x-ui.button>'))
        ->toContain('<a href="/en"', 'border-ink', 'rounded-full', '>Go</a>')
        ->and(Blade::render('<x-ui.button href="/en/membership/apply" variant="gold">Join</x-ui.button>'))
        ->toContain('bg-gold-500', 'text-ink-brand')
        ->and(Blade::render('<x-ui.button type="submit">Send</x-ui.button>'))
        ->toContain('<button type="submit"', 'bg-primary-600');
});

it('renders badges in each tone', function (string $tone, string $class) {
    expect(Blade::render('<x-ui.badge :tone="$tone">Label</x-ui.badge>', ['tone' => $tone]))->toContain($class, 'Label');
})->with([
    ['primary', 'bg-primary-50'],
    ['accent', 'bg-accent-50'],
    ['neutral', 'bg-surface-muted'],
    ['success', 'bg-success-50'],
    ['warning', 'bg-warning-50'],
    ['danger', 'bg-danger-50'],
    ['gold', 'bg-gold-50'],
    ['magenta', 'bg-magenta-50'],
    ['ink', 'bg-ink-brand'],
]);

it('renders cards with an optional link, badges and meta line', function () {
    $html = Blade::render(
        '<x-ui.card title="Rally" kicker="News" href="/en/news/1" :heading-level="2">Body<x-slot:badges>B</x-slot:badges><x-slot:meta>M</x-slot:meta></x-ui.card>',
    );

    expect($html)->toContain('<h2', '<a href="/en/news/1"', 'News', 'Body', 'B', 'M')
        ->and(Blade::render('<x-ui.card title="Plain" />'))->not->toContain('<a ');
});

it('renders a date tile with the Ethiopian day and month and the Gregorian date', function () {
    app()->setLocale('en');
    $html = Blade::render('<x-ui.date-tile :date="$date" />', ['date' => CarbonImmutable::parse('2026-09-11 10:00', 'Africa/Addis_Ababa')]);

    expect($html)->toContain('datetime="2026-09-11"', '>1<', 'Meskerem', '11 September 2026');
});
