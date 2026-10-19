<?php

namespace App\Domain\Organisation\Actions;

use App\Domain\Content\Contracts\TranslatableModel;
use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Support\BodyRenderer;
use App\Domain\Media\Actions\SyncMediaUsage;
use App\Domain\Translation\Support\Locales;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Writes the languages of an office, leader or league (ADR-0017). A
 * language whose name is emptied is removed; `published` switches a
 * language between draft and published for those allowed to publish.
 */
final class WriteTranslations
{
    public function __construct(private readonly SyncMediaUsage $usage) {}

    /**
     * @param  array<string, array<string, mixed>>  $translations
     */
    public static function requireName(array $translations): void
    {
        foreach ($translations as $values) {
            if (trim((string) ($values['name'] ?? '')) !== '') {
                return;
            }
        }

        throw ValidationException::withMessages(['data.translations.en.name' => __('organisation.validation.name_required')]);
    }

    /**
     * @param  array<string, array<string, mixed>>  $translations
     * @param  list<string>  $textFields  plain-text fields besides name and slug
     */
    public function handle(Model&TranslatableModel $record, array $translations, array $textFields, bool $canPublish): void
    {
        $existing = $record->translations()->get()->keyBy('locale');

        foreach (Locales::all() as $locale) {
            if (! array_key_exists($locale, $translations)) {
                continue;
            }
            $values = $translations[$locale];
            $current = $existing->get($locale);
            $name = trim((string) ($values['name'] ?? ''));

            if ($name === '') {
                $current?->delete();

                continue;
            }

            $attributes = [
                'name' => $name,
                'slug' => filled($values['slug'] ?? null) ? trim((string) $values['slug']) : $current?->slug,
                'body' => is_array($values['body'] ?? null) ? $values['body'] : null,
            ];
            foreach ($textFields as $field) {
                $attributes[$field] = filled($values[$field] ?? null) ? trim((string) $values[$field]) : null;
            }

            $wanted = ($values['published'] ?? false) ? ContentStatus::Published : ContentStatus::Draft;
            $attributes['status'] = $canPublish ? $wanted : ($current?->getAttribute('status') ?? ContentStatus::Draft);

            $record->putTranslation($locale, $attributes);
        }

        $bodyIds = [];
        foreach ($record->translations()->get() as $translation) {
            $body = $translation->getAttribute('body');
            $bodyIds = [...$bodyIds, ...BodyRenderer::mediaIds(is_array($body) ? $body : null)];
        }
        $this->usage->handle($record, 'body', $bodyIds);
    }

    /**
     * Social links kept only when they are https addresses of a known network.
     *
     * @param  list<string>  $networks
     * @return array<string, string>|null
     */
    public static function socials(mixed $value, array $networks): ?array
    {
        $links = [];
        foreach ($networks as $network) {
            $url = is_array($value) ? trim((string) ($value[$network] ?? '')) : '';
            if ($url === '') {
                continue;
            }
            if (! str_starts_with($url, 'https://') || filter_var($url, FILTER_VALIDATE_URL) === false) {
                throw ValidationException::withMessages(["data.socials.{$network}" => __('organisation.validation.https')]);
            }
            $links[$network] = $url;
        }

        return $links === [] ? null : $links;
    }

    public static function email(mixed $value): ?string
    {
        $email = trim((string) $value);
        if ($email === '') {
            return null;
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw ValidationException::withMessages(['data.email' => __('organisation.validation.email')]);
        }

        return $email;
    }
}
