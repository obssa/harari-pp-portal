<?php

use App\Domain\Access\Enums\SystemRole;
use App\Domain\Membership\Actions\SaveMember;
use App\Domain\Membership\Actions\SignInWithFayda;
use App\Domain\Membership\Models\Member;
use App\Domain\Organisation\Actions\SaveOffice;
use App\Domain\Organisation\Models\Office;
use App\Domain\Privacy\Actions\ErasePersonalData;
use App\Domain\Privacy\Support\PersonalDataSearch;
use App\Domain\Translation\Support\Locales;
use App\Filament\Pages\ManageIntegrations;
use App\Services\Fayda\FaydaClient;
use App\Services\Fayda\FaydaException;
use App\Settings\IntegrationSettings;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;

use function Pest\Livewire\livewire;

const ESIGNET = 'https://esignet.example.test';

/**
 * A fake eSignet: its own RSA key, the OpenID configuration, the key set,
 * and token and userinfo answers for a person with these claims.
 *
 * @param  array<string, mixed>  $person  claims of the signed-in person
 * @param  array<string, mixed>  $idToken  claims to change in the id_token
 */
function fakeEsignet(array $person = [], array $idToken = []): void
{
    static $pem = null;
    if ($pem === null) {
        openssl_pkey_export(openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]), $pem);
    }
    $details = openssl_pkey_get_details(openssl_pkey_get_private($pem));
    $b64 = fn (string $bytes): string => rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    $person = ['sub' => 'sample-psut-1', 'name' => 'Sample Fayda Person', 'phone_number' => '+251911777101', ...$person];

    Http::fake([
        ESIGNET.'/.well-known/openid-configuration' => Http::response([
            'issuer' => ESIGNET,
            'authorization_endpoint' => ESIGNET.'/authorize',
            'token_endpoint' => ESIGNET.'/v1/esignet/oauth/v2/token',
            'userinfo_endpoint' => ESIGNET.'/v1/esignet/oidc/userinfo',
            'jwks_uri' => ESIGNET.'/.well-known/jwks.json',
        ]),
        ESIGNET.'/.well-known/jwks.json' => Http::response(['keys' => [[
            'kty' => 'RSA', 'kid' => 'sample-idp-key', 'use' => 'sig',
            'n' => $b64($details['rsa']['n']), 'e' => $b64($details['rsa']['e']),
        ]]]),
        ESIGNET.'/v1/esignet/oauth/v2/token' => function (HttpRequest $request) use ($pem, $person, $idToken) {
            // The portal proves who it is with a JWT signed by its own key.
            $portal = openssl_pkey_get_details(openssl_pkey_get_private((string) app(IntegrationSettings::class)->fayda_private_key));
            $assertion = (array) JWT::decode((string) $request['client_assertion'], new Key($portal['key'], 'RS256'));
            expect($assertion['iss'])->toBe('sample-client')->and($assertion['aud'])->toBe(ESIGNET.'/v1/esignet/oauth/v2/token')
                ->and($request['code_verifier'])->toBeString()->and($request['redirect_uri'])->toBe(route('member.fayda.callback'));

            $nonce = (string) ($GLOBALS['esignet_nonce'] ?? 'none');

            return Http::response([
                'access_token' => 'sample-access-token',
                'token_type' => 'Bearer',
                'id_token' => JWT::encode([
                    'iss' => ESIGNET, 'aud' => 'sample-client', 'sub' => $person['sub'], 'nonce' => $nonce,
                    'iat' => time(), 'exp' => time() + 300, ...$idToken,
                ], $pem, 'RS256', 'sample-idp-key'),
            ]);
        },
        ESIGNET.'/v1/esignet/oidc/userinfo' => fn () => Http::response(
            JWT::encode($person, $pem, 'RS256', 'sample-idp-key'), 200, ['Content-Type' => 'application/jwt'],
        ),
    ]);
}

function configureFayda(): void
{
    $settings = app(IntegrationSettings::class);
    $settings->fayda_enabled = true;
    $settings->fayda_issuer = ESIGNET;
    $settings->fayda_client_id = 'sample-client';
    $settings->fayda_private_key = FaydaClient::newKey();
    $settings->save();
}

/**
 * @param  array<string, mixed>  $details
 */
function faydaMember(array $details = []): Member
{
    $woreda = app(SaveOffice::class)->handle(new Office, ['type' => 'woreda', 'translations' => ['en' => ['name' => 'Sample Woreda West', 'published' => true]]], staff(SystemRole::Publisher));

    return app(SaveMember::class)->handle(new Member, [
        'name' => 'Sample Fayda Member', 'sex' => 'female', 'id_type' => 'fayda', 'id_number' => 'FN7771010', 'phone' => '0911777101',
        'joined_on' => '2025-04-01', 'woreda_id' => $woreda->id, ...$details,
    ], staff(SystemRole::Admin));
}

/** Goes to eSignet and comes back with a code; returns the final response. */
function faydaRoundTrip(object $test, string $start = '/en/member/fayda', ?string $state = null): mixed
{
    $location = (string) $test->get($start)->assertRedirect()->headers->get('Location');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
    // eSignet puts the nonce it was sent into the id_token.
    $GLOBALS['esignet_nonce'] = $query['nonce'];

    return $test->get('/member/fayda/callback?'.http_build_query(['code' => 'sample-code', 'state' => $state ?? $query['state']]));
}

it('stays hidden and closed until it is configured', function () {
    $this->get('/en/member/sign-in')->assertOk()->assertDontSee(__('members.fayda.sign_in'));
    $this->get('/en/member/fayda')->assertNotFound();

    configureFayda();
    fakeEsignet();
    $this->get('/en/member/sign-in')->assertOk()->assertSee(__('members.fayda.sign_in'));
});

it('sends the member to eSignet with PKCE and the registered return address', function () {
    configureFayda();
    fakeEsignet();

    $location = (string) $this->get('/am/member/fayda')->assertRedirect()->headers->get('Location');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
    $flow = session('member.fayda');

    expect($location)->toStartWith(ESIGNET.'/authorize?')
        ->and($query)->toMatchArray([
            'response_type' => 'code', 'client_id' => 'sample-client', 'redirect_uri' => url('/member/fayda/callback'),
            'scope' => 'openid profile phone', 'code_challenge_method' => 'S256', 'ui_locales' => 'am en',
            'acr_values' => 'mosip:idp:acr:generated-code', 'state' => $flow['state'], 'nonce' => $flow['nonce'],
        ])
        ->and($query['code_challenge'])->toBe(FaydaClient::challenge($flow['verifier']))
        ->and($flow['locale'])->toBe('am');
});

it('signs a member in with Fayda, linking them the first time by the phone Fayda holds', function () {
    configureFayda();
    fakeEsignet();
    $member = faydaMember();

    faydaRoundTrip($this)->assertRedirect(route('member.home', ['locale' => 'en']));
    expect(Auth::guard('member')->id())->toBe($member->id)
        ->and($member->refresh()->fayda_sub_hash)->toBe(SignInWithFayda::subjectHash('sample-psut-1'))
        ->and($member->notes()->first()?->body)->toBe(__('members.note.fayda_linked'));

    // Next time the link is used, even after the phone on the record changed.
    Auth::guard('member')->logout();
    $member->forceFill(['phone' => '+251911777999', 'phone_hash' => hash('sha256', 'other')])->save();
    faydaRoundTrip($this)->assertRedirect(route('member.home', ['locale' => 'en']));
    expect(Auth::guard('member')->id())->toBe($member->id);
});

it('refuses a return nobody started or with a wrong state', function () {
    configureFayda();
    fakeEsignet();
    faydaMember();

    $default = Locales::default();
    $this->get('/member/fayda/callback?code=sample-code&state=forged')
        ->assertRedirect(route('member.sign-in', ['locale' => $default]))
        ->assertSessionHas('status', __('members.fayda.expired', [], $default));

    faydaRoundTrip($this, state: 'not-the-state-sent')->assertSessionHas('status', __('members.fayda.expired'));
    expect(Auth::guard('member')->check())->toBeFalse();
});

it('refuses an id_token made for another sign-in', function () {
    Exceptions::fake();
    configureFayda();
    fakeEsignet(idToken: ['nonce' => 'replayed']);
    faydaMember();

    faydaRoundTrip($this)
        ->assertRedirect(route('member.sign-in', ['locale' => 'en']))
        ->assertSessionHas('status', __('members.fayda.unavailable'));
    expect(Auth::guard('member')->check())->toBeFalse();
    Exceptions::assertReported(FaydaException::class);
});

it('tells a person with no member record to sign in with a code first', function () {
    configureFayda();
    fakeEsignet(['sub' => 'sample-psut-2', 'phone_number' => '+251911000555']);
    faydaMember();

    faydaRoundTrip($this)->assertSessionHas('status', __('members.fayda.no_member'));
    expect(Auth::guard('member')->check())->toBeFalse();
});

it('lets a signed-in member link and unlink their Fayda ID, one member per ID', function () {
    configureFayda();
    fakeEsignet(['phone_number' => null]);
    $member = faydaMember(['phone' => '0911777102', 'id_number' => 'FN7771020']);
    $this->actingAs($member, 'member');

    $this->get('/en/member')->assertOk()->assertSee(__('members.fayda.link'));
    faydaRoundTrip($this, '/en/member/fayda/link')
        ->assertRedirect(route('member.home', ['locale' => 'en']))
        ->assertSessionHas('status', __('members.fayda.linked'));
    expect($member->refresh()->fayda_sub_hash)->not->toBeNull();
    $this->get('/en/member')->assertSee(__('members.fayda.is_linked'));

    $other = faydaMember(['phone' => '0911777103', 'id_number' => 'FN7771030']);
    $this->actingAs($other, 'member');
    faydaRoundTrip($this, '/en/member/fayda/link')->assertSessionHas('status', __('members.fayda.taken'));
    expect($other->refresh()->fayda_sub_hash)->toBeNull();

    $this->actingAs($member, 'member')->post('/en/member/fayda/unlink')->assertRedirect(route('member.home', ['locale' => 'en']));
    expect($member->refresh()->fayda_sub_hash)->toBeNull();
});

it('lets the Super Admin make the portal key and shows only its public half', function () {
    $this->actingAs(staff(SystemRole::SuperAdmin));

    livewire(ManageIntegrations::class)
        ->callAction('faydaKey')
        ->assertNotified(__('admin.settings.integrations.fayda_key_made'));

    $settings = app(IntegrationSettings::class)->refresh();
    $jwk = app(FaydaClient::class)->publicJwk();
    expect($settings->fayda_private_key)->toContain('PRIVATE KEY')
        ->and($jwk)->toMatchArray(['kty' => 'RSA', 'alg' => 'RS256', 'use' => 'sig']);

    $page = $this->get(ManageIntegrations::getUrl())->assertOk()->assertSee($jwk['kid'])->assertSee(url('/member/fayda/callback'));
    expect($page->getContent())->not->toContain('PRIVATE KEY');
});

it('unlinks Fayda when a member is erased', function () {
    $member = faydaMember();
    app(SignInWithFayda::class)->link($member, 'sample-psut-9');

    app(ErasePersonalData::class)->handle(new PersonalDataSearch(null, '0911777101'), staff(SystemRole::Admin));
    expect($member->refresh()->fayda_sub_hash)->toBeNull();
});
