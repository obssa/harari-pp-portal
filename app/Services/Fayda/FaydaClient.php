<?php

namespace App\Services\Fayda;

use App\Settings\IntegrationSettings;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use stdClass;
use Throwable;

/**
 * Sign-in with Fayda through the National ID Program's eSignet (ADR-0058):
 * OpenID Connect with the authorization code, PKCE (S256) and a client
 * assertion signed with the portal's own RSA key (private_key_jwt). The
 * id_token and the signed userinfo answer are checked against eSignet's
 * published keys. Off until a Super Admin saves the address and client id
 * and switches it on.
 */
final class FaydaClient
{
    public const SCOPES = 'openid profile phone';

    /** Seconds of clock difference accepted between the portal and eSignet. */
    public const LEEWAY = 60;

    public function __construct(private readonly IntegrationSettings $settings) {}

    public function isConfigured(): bool
    {
        return $this->settings->fayda_enabled
            && filled($this->settings->fayda_issuer)
            && filled($this->settings->fayda_client_id)
            && filled($this->settings->fayda_private_key);
    }

    /** The one address to register with eSignet; the language travels in the session. */
    public static function redirectUri(): string
    {
        return route('member.fayda.callback');
    }

    /**
     * A new state, nonce and PKCE verifier for one sign-in.
     *
     * @return array{state: string, nonce: string, verifier: string}
     */
    public static function newFlow(): array
    {
        return ['state' => Str::random(40), 'nonce' => Str::random(40), 'verifier' => Str::random(64)];
    }

    /**
     * @param  array{state: string, nonce: string, verifier: string}  $flow
     */
    public function authorizationUrl(array $flow, string $locale): string
    {
        $config = $this->configuration();
        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => (string) $this->settings->fayda_client_id,
            'redirect_uri' => self::redirectUri(),
            'scope' => self::SCOPES,
            'state' => $flow['state'],
            'nonce' => $flow['nonce'],
            'code_challenge' => self::challenge($flow['verifier']),
            'code_challenge_method' => 'S256',
            'acr_values' => (string) $this->settings->fayda_acr_values,
            'ui_locales' => $locale === 'en' ? 'en' : $locale.' en',
            'claims' => json_encode(['userinfo' => [
                'name' => ['essential' => true],
                'phone_number' => ['essential' => false],
            ], 'id_token' => new stdClass], JSON_THROW_ON_ERROR),
        ], '', '&', PHP_QUERY_RFC3986);

        return $config['authorization_endpoint'].'?'.$query;
    }

    /**
     * Exchanges the code for the person's claims.
     *
     * @param  array{state: string, nonce: string, verifier: string}  $flow
     * @return array{sub: string, name: ?string, phone_number: ?string, phone_number_verified: ?bool}
     */
    public function exchange(string $code, array $flow): array
    {
        $config = $this->configuration();
        $clientId = (string) $this->settings->fayda_client_id;

        $answer = Http::asForm()->acceptJson()->timeout(15)->post($config['token_endpoint'], [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => self::redirectUri(),
            'client_id' => $clientId,
            'client_assertion_type' => 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer',
            'client_assertion' => $this->clientAssertion($config['token_endpoint']),
            'code_verifier' => $flow['verifier'],
        ]);
        $idToken = $answer->json('id_token');
        $accessToken = $answer->json('access_token');
        if (! $answer->successful() || ! is_string($idToken) || ! is_string($accessToken)) {
            throw new FaydaException('The token endpoint refused the code ('.$answer->status().').');
        }

        $identity = $this->verify($idToken, $config['jwks_uri']);
        $audience = (array) ($identity['aud'] ?? []);
        if (($identity['iss'] ?? null) !== $config['issuer'] || ! in_array($clientId, $audience, true)
            || ! is_string($identity['nonce'] ?? null) || ! hash_equals($flow['nonce'], $identity['nonce'])
            || ! is_string($identity['sub'] ?? null) || $identity['sub'] === '') {
            throw new FaydaException('The id_token is not for this sign-in.');
        }

        $info = Http::withToken($accessToken)->timeout(15)->get($config['userinfo_endpoint']);
        if (! $info->successful()) {
            throw new FaydaException('The userinfo endpoint refused the token ('.$info->status().').');
        }
        $body = trim($info->body());
        $claims = str_starts_with($body, '{') ? (array) $info->json() : $this->verify($body, $config['jwks_uri']);
        if (($claims['sub'] ?? null) !== $identity['sub']) {
            throw new FaydaException('The userinfo answer is for someone else.');
        }

        $verified = $claims['phone_number_verified'] ?? null;

        return [
            'sub' => $identity['sub'],
            'name' => is_string($claims['name'] ?? null) ? $claims['name'] : null,
            'phone_number' => is_string($claims['phone_number'] ?? null) ? $claims['phone_number'] : null,
            'phone_number_verified' => is_bool($verified) ? $verified : null,
        ];
    }

    /**
     * eSignet's OpenID configuration, read from its well-known address.
     *
     * @return array{issuer: string, authorization_endpoint: string, token_endpoint: string, userinfo_endpoint: string, jwks_uri: string}
     */
    public function configuration(): array
    {
        $base = rtrim((string) $this->settings->fayda_issuer, '/');

        /** @var array{issuer: string, authorization_endpoint: string, token_endpoint: string, userinfo_endpoint: string, jwks_uri: string} */
        return Cache::remember('fayda:configuration:'.hash('sha256', $base), 3600, function () use ($base): array {
            try {
                $config = Http::acceptJson()->timeout(10)->get($base.'/.well-known/openid-configuration')->throw()->json();
            } catch (Throwable $e) {
                throw new FaydaException('eSignet could not be reached.', previous: $e);
            }

            $wanted = [];
            foreach (['issuer', 'authorization_endpoint', 'token_endpoint', 'userinfo_endpoint', 'jwks_uri'] as $field) {
                $value = is_array($config) ? ($config[$field] ?? null) : null;
                if (! is_string($value) || $value === '') {
                    throw new FaydaException("eSignet's configuration has no {$field}.");
                }
                $wanted[$field] = $value;
            }

            return $wanted;
        });
    }

    /**
     * A new RSA key for the portal, as PEM.
     */
    public static function newKey(): string
    {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
        if ($key === false || ! openssl_pkey_export($key, $pem)) {
            throw new FaydaException('No RSA key could be made.');
        }

        return (string) $pem;
    }

    /**
     * The public half of the portal's key as a JWK, to register with eSignet.
     * Its kid is the RFC 7638 thumbprint.
     *
     * @return array{kty: string, use: string, alg: string, kid: string, n: string, e: string}|null
     */
    public function publicJwk(): ?array
    {
        $pem = $this->settings->fayda_private_key;
        $key = filled($pem) ? openssl_pkey_get_private((string) $pem) : false;
        $details = $key !== false ? openssl_pkey_get_details($key) : false;
        if (! is_array($details) || ! isset($details['rsa']['n'], $details['rsa']['e'])) {
            return null;
        }

        $n = self::base64url((string) $details['rsa']['n']);
        $e = self::base64url((string) $details['rsa']['e']);
        $kid = self::base64url(hash('sha256', '{"e":"'.$e.'","kty":"RSA","n":"'.$n.'"}', true));

        return ['kty' => 'RSA', 'use' => 'sig', 'alg' => 'RS256', 'kid' => $kid, 'n' => $n, 'e' => $e];
    }

    public static function challenge(string $verifier): string
    {
        return self::base64url(hash('sha256', $verifier, true));
    }

    private function clientAssertion(string $tokenEndpoint): string
    {
        $clientId = (string) $this->settings->fayda_client_id;
        $now = time();

        return JWT::encode([
            'iss' => $clientId,
            'sub' => $clientId,
            'aud' => $tokenEndpoint,
            'iat' => $now,
            'exp' => $now + 300,
            'jti' => Str::random(32),
        ], (string) $this->settings->fayda_private_key, 'RS256', $this->publicJwk()['kid'] ?? null);
    }

    /**
     * The claims of a JWT signed with one of eSignet's keys. The keys are
     * read again once when the token names a key not seen before.
     *
     * @return array<string, mixed>
     */
    private function verify(string $jwt, string $jwksUri): array
    {
        JWT::$leeway = self::LEEWAY;

        foreach ([false, true] as $fresh) {
            try {
                return json_decode((string) json_encode(JWT::decode($jwt, $this->keys($jwksUri, $fresh))), true, flags: JSON_THROW_ON_ERROR);
            } catch (Throwable $e) {
                if ($fresh) {
                    throw new FaydaException('A token from eSignet failed its check: '.$e->getMessage(), previous: $e);
                }
            }
        }

        throw new FaydaException('A token from eSignet failed its check.');
    }

    /**
     * @return array<string, Key>
     */
    private function keys(string $jwksUri, bool $fresh): array
    {
        $cacheKey = 'fayda:jwks:'.hash('sha256', $jwksUri);
        if ($fresh) {
            Cache::forget($cacheKey);
        }

        /** @var array<string, mixed> $set */
        $set = Cache::remember($cacheKey, 3600, fn (): array => (array) Http::acceptJson()->timeout(10)->get($jwksUri)->throw()->json());

        return JWK::parseKeySet($set, 'RS256');
    }

    private static function base64url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
