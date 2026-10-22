<?php

use App\Domain\Access\Enums\SystemRole;
use App\Domain\Organisation\Actions\SaveLeader;
use App\Domain\Organisation\Enums\LeaderBody;
use App\Domain\Organisation\Models\Leader;
use App\Support\Initials;

function samplePerson(string $body, string $name, string $position, int $rank = 10): Leader
{
    return app(SaveLeader::class)->handle(new Leader, ['body' => $body, 'rank' => $rank, 'translations' => [
        'en' => ['name' => $name, 'position' => $position, 'summary' => 'Sample summary.', 'published' => true],
    ]], staff(SystemRole::Publisher, mfa: false));
}

it('lists the region\'s government first, then the secretariat and the rest, with the branch head featured', function () {
    samplePerson('office_heads', 'Sample Sector Head', 'Sample head of a sector');
    samplePerson('secretariat', 'Sample Branch Head', 'Sample head of the branch office', 1);
    samplePerson('government', 'Sample President', 'Sample president of the region', 1);

    $html = (string) $this->get('/en/leaders')->assertOk()->getContent();

    $government = strpos($html, 'id="body-government"');
    $secretariat = strpos($html, 'id="body-secretariat"');
    $heads = strpos($html, 'id="body-office_heads"');
    expect($government)->toBeInt()->toBeLessThan($secretariat)
        ->and($secretariat)->toBeLessThan($heads)
        ->and($html)->toContain(__('leaders.featured'), 'href="#body-government"', e(LeaderBody::Government->description('en')))
        ->and(strpos($html, __('leaders.featured')))->toBeLessThan($government);
});

it('draws initials without honorifics for people without a photo', function () {
    expect(Initials::of('Getu Weyesa'))->toBe('GW')
        ->and(Initials::of('አቶ ጌቱ ወዬሳ'))->toBe('ጌወ')
        ->and(Initials::of('H.E Getu Wayyesa Tola'))->toBe('GW')
        ->and(Initials::of('ክቡር አቶ ጌቱ ወዬሳ ቶላ'))->toBe('ጌወ')
        ->and(Initials::of('Kabajamoo Obbo Geetuu Wayyeessaa Tolaa'))->toBe('GW')
        ->and(Initials::of('Obbo Geetuu Wayyeessaa'))->toBe('GW')
        ->and(Initials::of('Ordin'))->toBe('O');
});
