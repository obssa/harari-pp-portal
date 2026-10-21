<?php

use App\Domain\Access\Enums\SystemRole;
use App\Domain\Notifications\Channels\TelegramChannel;
use App\Domain\Notifications\Enums\NoticeKind;
use App\Domain\Notifications\Support\NoticePreferences;
use App\Domain\Notifications\Support\Recipients;
use App\Domain\Volunteers\Models\Volunteer;
use App\Domain\Volunteers\Notifications\NewVolunteerNotice;
use App\Filament\Pages\NotificationCentre;
use App\Services\Telegram\TelegramBot;
use App\Settings\IntegrationSettings;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

use function Pest\Livewire\livewire;

function telegramOn(): void
{
    $settings = app(IntegrationSettings::class);
    $settings->telegram_bot_token = '123:sample-token';
    $settings->save();
    Cache::forget('telegram-bot-username');
    Http::fake([
        'api.telegram.org/bot123:sample-token/getMe' => Http::response(['ok' => true, 'result' => ['username' => 'SampleHppBot']]),
        'api.telegram.org/*' => Http::response(['ok' => true]),
    ]);
}

/** An update from Telegram, as its webhook sends it. */
function telegramUpdate(string $text, string $chat = '987654', ?string $secret = null): TestResponse
{
    return test()->postJson('/telegram/webhook', ['message' => ['chat' => ['id' => $chat], 'text' => $text]],
        ['X-Telegram-Bot-Api-Secret-Token' => $secret ?? TelegramBot::webhookSecret()]);
}

it('offers nothing about Telegram until the bot is set up', function () {
    $user = staff(SystemRole::MembershipOfficer);
    $user->forceFill(['telegram_chat_id' => '987654'])->save();
    NoticePreferences::save($user, [NoticeKind::Volunteers->value => ['in_app', 'telegram']]);

    expect(NoticePreferences::via($user->refresh(), NoticeKind::Volunteers))->not->toContain(TelegramChannel::class);
    $this->actingAs($user);
    livewire(NotificationCentre::class)->assertActionHidden('telegram');
});

it('links a staff account with a one-time code through the bot', function () {
    telegramOn();
    $user = staff(SystemRole::MembershipOfficer, attributes: ['name' => 'Sample Officer']);
    $this->actingAs($user);

    livewire(NotificationCentre::class)->assertActionVisible('telegram')->mountAction('telegram');
    $code = (string) $user->refresh()->telegram_link_code;
    expect($code)->toHaveLength(10);

    telegramUpdate("/start {$code}", secret: 'wrong')->assertForbidden();
    telegramUpdate("/start {$code}")->assertNoContent();
    expect($user->refresh()->telegram_chat_id)->toBe('987654')->and($user->telegram_link_code)->toBeNull()
        ->and(json_encode(DB::table('users')->where('id', $user->id)->first()))->not->toContain('987654');
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'sendMessage') && $request['chat_id'] === '987654'
        && str_contains((string) $request['text'], 'Sample Officer'));

    telegramUpdate("/start {$code}", '111')->assertNoContent();
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'sendMessage') && $request['chat_id'] === '111'
        && $request['text'] === __('notifications.telegram.unknown_code'));

    telegramUpdate('/stop')->assertNoContent();
    expect($user->refresh()->telegram_chat_id)->toBeNull();
});

it('sends notices to Telegram for the kinds a person chose there', function () {
    telegramOn();
    $user = staff(SystemRole::MembershipOfficer);
    $user->forceFill(['telegram_chat_id' => '555'])->save();
    NoticePreferences::save($user, [NoticeKind::Volunteers->value => ['telegram']]);
    expect(NoticePreferences::via($user->refresh(), NoticeKind::Volunteers))->toBe([TelegramChannel::class]);

    $volunteer = new Volunteer(['name' => 'Sample', 'skills' => [], 'availability' => [], 'languages' => [], 'locale' => 'en']);
    $volunteer->save();
    Recipients::notify([$user], NewVolunteerNotice::for($volunteer));

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'sendMessage') && $request['chat_id'] === '555'
        && str_contains((string) $request['text'], __('volunteers.notice.title')) && str_contains((string) $request['text'], '/admin/'));

    $this->actingAs($user);
    livewire(NotificationCentre::class)->callAction('telegram');
    expect($user->refresh()->telegram_chat_id)->toBeNull();
});

it('registers the webhook with the secret', function () {
    telegramOn();
    $this->artisan('telegram:webhook')->assertSuccessful();
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'setWebhook')
        && $request['secret_token'] === TelegramBot::webhookSecret() && str_ends_with((string) $request['url'], '/telegram/webhook'));
});
