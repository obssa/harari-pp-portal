<?php

namespace App\Domain\Api\Actions;

use App\Domain\Api\Enums\ApiScope;
use App\Domain\Api\Models\ApiKey;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Issues, changes and revokes API keys (ADR-0056). A new key's text is
 * returned once and never stored.
 */
final class IssueApiKey
{
    /**
     * @param  array<string, mixed>  $data  name, scopes, rate_per_minute, notes, expires_at
     * @return array{0: ApiKey, 1: string} the key and its text
     */
    public function handle(array $data, User $actor): array
    {
        $plain = ApiKey::PREFIX.Str::random(40);

        $key = new ApiKey;
        $this->fill($key, $data);
        $key->prefix = substr($plain, 0, 12);
        $key->key_hash = ApiKey::hash($plain);
        $key->created_by = $actor->id;
        $key->save();

        return [$key, $plain];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ApiKey $key, array $data): ApiKey
    {
        $this->fill($key, $data);
        $key->save();

        return $key;
    }

    public function revoke(ApiKey $key): void
    {
        $key->revoked_at = CarbonImmutable::now();
        $key->save();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function fill(ApiKey $key, array $data): void
    {
        $rate = (int) ($data['rate_per_minute'] ?? config('portal.api.default_rate'));
        $expires = $data['expires_at'] ?? null;
        $notes = $data['notes'] ?? null;

        $key->name = trim((string) ($data['name'] ?? ''));
        $key->scopes = ApiScope::clean((array) ($data['scopes'] ?? []));
        $key->rate_per_minute = max(1, min($rate, (int) config('portal.api.max_rate')));
        $key->notes = is_string($notes) && trim($notes) !== '' ? trim($notes) : null;
        $key->expires_at = is_string($expires) && $expires !== '' ? CarbonImmutable::parse($expires) : null;
    }
}
