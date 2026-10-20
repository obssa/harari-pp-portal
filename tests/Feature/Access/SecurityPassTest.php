<?php

use App\Domain\Access\Enums\SystemRole;
use App\Domain\Access\Notifications\NewSignInNotice;
use App\Domain\Health\Checks\ErrorLogCheck;
use App\Domain\Health\Support\RecentErrors;
use App\Domain\Social\Actions\CheckFacebookTokens;
use App\Domain\Social\Enums\Platform;
use App\Domain\Social\Models\SocialSource;
use App\Domain\Social\Notifications\TokenExpiringNotice;
use App\Filament\Resources\SocialSources\Pages\ManageSocialSources;
use App\Models\User;
use App\Support\UserAgent;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

use function Pest\Livewire\livewire;

const CHROME_WINDOWS = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0 Safari/537.36';
const SAFARI_IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1';

/** A sign-in as the guard reports it, from a given address and browser. */
function signInFrom(User $user, string $ip, string $agent): void
{
    request()->server->set('REMOTE_ADDR', $ip);
    request()->headers->set('User-Agent', $agent);
    event(new Login('web', $user, false));
}

function facebookPage(array $credentials = ['token' => 'sample-page-token']): SocialSource
{
    return SocialSource::query()->create(['platform' => Platform::Facebook, 'handle' => 'samplepage', 'name' => 'Sample page', 'credentials' => $credentials]);
}

it('keeps the admin to the listed addresses when a list is set', function () {
    $this->get('/admin/login')->assertOk();

    config(['portal.admin_allowed_ips' => '10.0.0.0/8, 192.0.2.7']);
    $this->get('/admin/login')->assertForbidden();
    $this->withServerVariables(['REMOTE_ADDR' => '10.20.30.40'])->get('/admin/login')->assertOk();
    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.7'])->get('/admin/login')->assertOk();
    $this->get('/en')->assertOk();
});

it('alerts a person to a sign-in from a new network and browser, not to familiar ones', function () {
    Notification::fake();
    $user = staff(SystemRole::Editor);

    signInFrom($user, '102.218.51.10', CHROME_WINDOWS);
    signInFrom($user, '102.218.51.200', SAFARI_IPHONE);
    signInFrom($user, '196.188.1.1', CHROME_WINDOWS);
    Notification::assertNothingSent();

    signInFrom($user, '196.190.7.7', SAFARI_IPHONE.' changed');
    Notification::assertSentTo($user, NewSignInNotice::class, fn (NewSignInNotice $n, array $channels): bool => $n->ip === '196.190.7.7'
        && $channels === ['database', 'mail']);
});

it('says when, how and from where in the alert', function () {
    $user = staff(SystemRole::Editor);
    $notice = new NewSignInNotice('196.190.7.7', CHROME_WINDOWS, '2026-10-20T09:30:00+00:00');

    $stored = $notice->toDatabase($user);
    expect($stored['title'])->toBe(__('access.sign_in_alert.title'))
        ->and($stored['body'])->toContain('Chrome on Windows')->toContain('196.190.7.7')
        ->and($stored['kind'])->toBe('security');
});

it('names browsers and systems roughly', function (?string $agent, string $name) {
    expect(UserAgent::describe($agent))->toBe($name);
})->with([
    [CHROME_WINDOWS, 'Chrome on Windows'],
    [SAFARI_IPHONE, 'Safari on iOS'],
    ['Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0 Mobile Safari/537.36 EdgA/141.0 Edg/141.0', 'Edge on Android'],
    ['Mozilla/5.0 (X11; Linux x86_64; rv:140.0) Gecko/20100101 Firefox/140.0', 'Firefox on Linux'],
    ['curl/8.0', 'an unknown browser'],
    [null, 'an unknown browser'],
]);

it('warns social media managers as a page token nears its end, once per stage', function () {
    Notification::fake();
    $manager = staff(SystemRole::MediaManager);
    $source = facebookPage();
    $expires = now()->addDays(10)->getTimestamp();
    Http::fake(['graph.facebook.com/*' => function () use (&$expires) {
        return Http::response(['data' => ['is_valid' => true, 'expires_at' => $expires, 'data_access_expires_at' => 0]]);
    }]);

    expect(app(CheckFacebookTokens::class)->handle())->toBe(['checked' => 1, 'warned' => 1]);
    app(CheckFacebookTokens::class)->handle();
    Notification::assertSentToTimes($manager, TokenExpiringNotice::class, 1);

    $expires = now()->addDays(5)->getTimestamp();
    app(CheckFacebookTokens::class)->handle();
    Notification::assertSentToTimes($manager, TokenExpiringNotice::class, 2);

    expect(CheckFacebookTokens::describe($source->refresh()))->toStartWith('Meta says it works until');
});

it('warns when Meta no longer accepts a token, and stays quiet for one that never expires', function () {
    Notification::fake();
    $manager = staff(SystemRole::MediaManager);
    $source = facebookPage();
    $answer = ['error' => ['message' => 'Invalid OAuth access token.']];
    $status = 400;
    Http::fake(['graph.facebook.com/*' => function () use (&$answer, &$status) {
        return Http::response($answer, $status);
    }]);

    app(CheckFacebookTokens::class)->handle();
    Notification::assertSentTo($manager, TokenExpiringNotice::class, fn (TokenExpiringNotice $n): bool => $n->expiresAt === null);
    expect(CheckFacebookTokens::describe($source->refresh()))->toBe(__('social.source.token_invalid'));

    $answer = ['data' => ['is_valid' => true, 'expires_at' => 0]];
    $status = 200;
    app(CheckFacebookTokens::class)->handle();
    expect(CheckFacebookTokens::describe($source->refresh()))->toBe(__('social.source.token_never'))
        ->and($source->credentials['token_warned'])->toBeNull();
    Notification::assertSentToTimes($manager, TokenExpiringNotice::class, 1);

    $answer = 'down';
    $status = 503;
    expect(CheckFacebookTokens::inspect('sample'))->toBeNull();
});

it('checks a new page token as soon as it is saved', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(['data' => ['is_valid' => true, 'expires_at' => now()->addDays(60)->getTimestamp()]])]);
    $source = facebookPage(['token_warned' => 0]);
    $this->actingAs(staff(SystemRole::MediaManager));

    livewire(ManageSocialSources::class)
        ->callAction(TestAction::make('token')->table($source), data: ['token' => 'new-sample-token'])
        ->assertHasNoActionErrors();

    $credentials = $source->refresh()->credentials;
    expect($credentials['token'])->toBe('new-sample-token')
        ->and($credentials['token_valid'])->toBeTrue()
        ->and($credentials['token_expires_at'])->not->toBeNull()
        ->and($credentials['token_warned'])->toBeNull();
});

it('reads the newest errors from the log without their context', function () {
    $dir = sys_get_temp_dir().'/portal-logs-'.uniqid();
    File::ensureDirectoryExists($dir);
    $recent = now()->subHours(2)->format('Y-m-d H:i:s');
    $old = now()->subDays(3)->format('Y-m-d H:i:s');
    File::put("{$dir}/laravel-sample.log", implode("\n", [
        "[{$old}] production.ERROR: Sample old failure {\"exception\":\"[object] (RuntimeException(code: 0))\"}",
        "[{$recent}] production.INFO: Sample note",
        "[{$recent}] production.ERROR: Sample recent failure {\"userId\":1,\"exception\":\"[object] ...\"}",
        '#0 /home/sample/vendor/laravel/framework/src/Illuminate/Foundation/Application.php(123): handle()',
        '',
    ]));

    $errors = RecentErrors::read(10, null, $dir);
    expect($errors)->toHaveCount(2)
        ->and($errors[0]['message'])->toBe('Sample recent failure')
        ->and($errors[0]['level'])->toBe('ERROR')
        ->and(RecentErrors::countSince(now()->subDay(), $dir))->toBe(1)
        ->and(ErrorLogCheck::new()->directory($dir)->run()->status->value)->toBe('warning')
        ->and(ErrorLogCheck::new()->directory($dir)->failWhenAtLeast(1)->run()->status->value)->toBe('failed')
        ->and(ErrorLogCheck::new()->directory("{$dir}/none")->run()->status->value)->toBe('ok');

    File::deleteDirectory($dir);
});

it('never sends personal data or request bodies to the error tracker', function () {
    expect(config('sentry.send_default_pii'))->toBeFalse()
        ->and(config('sentry.max_request_body_size'))->toBe('never');
});
