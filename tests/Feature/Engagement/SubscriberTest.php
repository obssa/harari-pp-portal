<?php

use App\Domain\Access\Enums\SystemRole;
use App\Domain\Engagement\Actions\Subscribe;
use App\Domain\Engagement\Mail\ConfirmSubscriptionMail;
use App\Domain\Engagement\Models\Subscriber;
use App\Domain\Privacy\Actions\ErasePersonalData;
use App\Domain\Privacy\Actions\ExportPersonalData;
use App\Domain\Privacy\Support\PersonalDataSearch;
use App\Filament\Resources\Subscribers\Pages\ListSubscribers;
use App\Filament\Resources\Subscribers\SubscriberResource;
use App\Services\Sms\SendSms;
use App\Support\SpamGuard;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

use function Pest\Livewire\livewire;

/** What a person sends from the newsletter page. */
function subscription(array $overrides = []): array
{
    return [
        'channels' => ['email'], 'email' => 'Reader@Example.org', 'phone' => '', 'topics' => ['news', 'events'], 'consent' => '1',
        SpamGuard::STARTED => Crypt::encryptString((string) (time() - 10)),
        ...$overrides,
    ];
}

/** The private link the last confirmation email carried. */
function confirmationToken(): string
{
    $token = '';
    Mail::assertQueued(ConfirmSubscriptionMail::class, function (ConfirmSubscriptionMail $mail) use (&$token): bool {
        $token = $mail->token;

        return true;
    });

    return $token;
}

beforeEach(function () {
    Mail::fake();
    Bus::fake([SendSms::class]);
});

it('subscribes by email with a confirmation link that opens the preferences', function () {
    $this->get('/en/newsletter?email=reader@example.org')->assertOk()->assertSee('value="reader@example.org"', escape: false)
        ->assertHeader('Cache-Control', 'max-age=0, no-store, private');
    $this->post('/en/newsletter', subscription())->assertRedirect(route('newsletter.check', ['locale' => 'en']));
    $this->get('/en/newsletter/check')->assertOk()->assertSee(__('newsletter.check_email'));

    $subscriber = Subscriber::query()->sole();
    expect($subscriber->email)->toBe('reader@example.org')
        ->and($subscriber->email_hash)->toBe(Subscriber::hash('reader@example.org'))
        ->and($subscriber->reachableBy('email'))->toBeFalse()
        ->and(json_encode((array) DB::table('subscribers')->first()))->not->toContain('reader@example.org');

    $token = confirmationToken();
    $this->get("/en/newsletter/confirm/{$token}")->assertRedirect(route('newsletter.preferences', ['locale' => 'en', 'token' => $token]));
    $this->get("/en/newsletter/preferences/{$token}")->assertOk()->assertSee('reader@example.org')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    expect($subscriber->refresh()->reachableBy('email'))->toBeTrue()
        ->and(Subscriber::query()->reachable('email')->count())->toBe(1)
        ->and(Subscriber::query()->reachable('sms')->count())->toBe(0);
    $this->get('/en/newsletter/preferences/'.str_repeat('x', 40))->assertNotFound();
});

it('subscribes a phone with a code by SMS', function () {
    $this->post('/en/newsletter', subscription(['channels' => ['sms'], 'email' => '', 'phone' => '0911 22 33 44']))->assertRedirect(route('newsletter.check', ['locale' => 'en']));
    $this->get('/en/newsletter/check')->assertSee('+251••••••344');

    $code = '';
    Bus::assertDispatchedSync(SendSms::class, function (SendSms $sms) use (&$code): bool {
        preg_match('/\b(\d{6})\b/', $sms->text, $m);
        $code = $m[1] ?? '';

        return true;
    });
    $this->post('/en/newsletter/check', ['code' => '000000'])->assertSessionHasErrors('code');
    $subscriber = Subscriber::query()->sole();
    $this->post('/en/newsletter/check', ['code' => $code])->assertRedirect(route('newsletter.preferences', ['locale' => 'en', 'token' => $subscriber->token]));
    expect($subscriber->refresh()->reachableBy('sms'))->toBeTrue()->and($subscriber->reachableBy('email'))->toBeFalse();
    Mail::assertNothingQueued();
});

it('never says that an address is already on the list and keeps the existing choices', function () {
    $this->post('/en/newsletter', subscription());
    $first = Subscriber::query()->sole();
    app(Subscribe::class)->confirmEmail($first);

    $this->post('/am/newsletter', subscription(['topics' => ['opportunities']]))->assertRedirect(route('newsletter.check', ['locale' => 'am']));
    expect(Subscriber::query()->count())->toBe(1)
        ->and($first->refresh()->topics)->toBe(['news', 'events'])
        ->and($first->locale)->toBe('en');
    Mail::assertQueued(ConfirmSubscriptionMail::class, fn (ConfirmSubscriptionMail $mail): bool => $mail->alreadyConfirmed);
});

it('refuses incomplete sign-ups', function (array $overrides, string $field) {
    expect(fn () => app(Subscribe::class)->handle(subscription($overrides), 'en'))->toThrow(ValidationException::class);
    $this->post('/en/newsletter', subscription($overrides))->assertSessionHasErrors($field);
})->with([
    'no channel' => [['channels' => []], 'channels'],
    'email channel without email' => [['email' => ''], 'email'],
    'sms channel without phone' => [['channels' => ['sms'], 'email' => ''], 'phone'],
    'bad phone' => [['channels' => ['sms'], 'phone' => '12'], 'phone'],
    'no topic' => [['topics' => []], 'topics'],
    'no consent' => [['consent' => null], 'consent'],
]);

it('changes preferences, unsubscribes and subscribes again from the preference centre', function () {
    $this->post('/en/newsletter', subscription());
    $subscriber = Subscriber::query()->sole();
    app(Subscribe::class)->confirmEmail($subscriber);
    $token = $subscriber->token;

    $this->post("/en/newsletter/preferences/{$token}", ['channels' => ['email'], 'topics' => ['statements'], 'locale' => 'om'])->assertSessionHas('status', __('newsletter.saved'));
    expect($subscriber->refresh()->topics)->toBe(['statements'])->and($subscriber->locale)->toBe('om');

    $this->post("/en/newsletter/preferences/{$token}", ['unsubscribe' => '1']);
    expect($subscriber->refresh()->unsubscribed_at)->not->toBeNull()->and($subscriber->reachableBy('email'))->toBeFalse();

    $this->post("/en/newsletter/preferences/{$token}", ['channels' => ['email'], 'topics' => ['news']]);
    expect($subscriber->refresh()->reachableBy('email'))->toBeTrue();
});

it('lets a mail app unsubscribe with one click, without a session', function () {
    $this->post('/en/newsletter', subscription());
    $subscriber = Subscriber::query()->sole();
    app(Subscribe::class)->confirmEmail($subscriber);

    $this->call('POST', "/en/newsletter/unsubscribe/{$subscriber->token}", ['List-Unsubscribe' => 'One-Click'])
        ->assertOk()->assertSee(__('newsletter.unsubscribed_title'))->assertCookieMissing(config('session.cookie'));
    expect($subscriber->refresh()->unsubscribed_at)->not->toBeNull();
    $this->post('/en/newsletter/unsubscribe/'.str_repeat('y', 40))->assertNotFound();
});

it('offers the sign-up in the footer of every page', function () {
    $this->get('/en')->assertOk()->assertSee('action="'.route('newsletter.create', ['locale' => 'en']).'"', escape: false);
});

it('lists subscribers masked for communications staff', function () {
    $this->post('/en/newsletter', subscription());
    $this->actingAs(staff(SystemRole::CommunicationsOfficer));
    $this->get(SubscriberResource::getUrl('index'))->assertOk()->assertSee('r•••@example.org')->assertDontSee('reader@example.org');
    livewire(ListSubscribers::class)->assertActionHidden('export');

    $this->actingAs(staff(SystemRole::Admin));
    livewire(ListSubscribers::class)->callAction('export')->assertFileDownloaded('subscribers-'.now()->format('Y-m-d').'.csv');
    $this->actingAs(staff(SystemRole::Editor))->get(SubscriberResource::getUrl('index'))->assertForbidden();
});

it('finds, copies and deletes subscriptions in privacy requests, and drops unconfirmed sign-ups after a week', function () {
    $this->post('/en/newsletter', subscription());
    $search = new PersonalDataSearch('reader@example.org', null);
    expect($search->counts())->toMatchArray(['subscribers' => 1]);
    expect(app(ExportPersonalData::class)->handle($search, staff(SystemRole::Admin))['newsletter'][0])
        ->toMatchArray(['email' => 'reader@example.org', 'topics' => ['news', 'events']]);
    app(ErasePersonalData::class)->handle($search, staff(SystemRole::Admin));
    expect(Subscriber::query()->count())->toBe(0);

    $this->post('/en/newsletter', subscription(['email' => 'second@example.org']));
    $this->post('/en/newsletter', subscription(['email' => 'third@example.org']));
    Subscriber::query()->get()->each(fn (Subscriber $s) => $s->forceFill(['created_at' => now()->subDays(Subscribe::UNCONFIRMED_DAYS + 1)])->save());
    app(Subscribe::class)->confirmEmail(Subscriber::query()->where('email_hash', Subscriber::hash('third@example.org'))->sole());
    $this->artisan('membership:purge-applications')->assertSuccessful();
    expect(Subscriber::query()->count())->toBe(1);
});
