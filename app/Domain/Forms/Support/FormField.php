<?php

namespace App\Domain\Forms\Support;

use App\Domain\Translation\Support\Locales;

/**
 * One question of a form (ADR-0028). Its label, help text and choices are
 * stored with it in every language, so they cannot drift apart; choices are
 * matched by their position, the same in every language.
 */
final readonly class FormField
{
    public const TEXT = 'text';

    public const TEXTAREA = 'textarea';

    public const EMAIL = 'email';

    public const PHONE = 'phone';

    public const NUMBER = 'number';

    public const DATE = 'date';

    public const SELECT = 'select';

    public const RADIO = 'radio';

    public const CHECKBOXES = 'checkboxes';

    public const CONSENT = 'consent';

    public const FILE = 'file';

    public const TYPES = [self::TEXT, self::TEXTAREA, self::EMAIL, self::PHONE, self::NUMBER, self::DATE, self::SELECT, self::RADIO, self::CHECKBOXES, self::CONSENT, self::FILE];

    public const WITH_CHOICES = [self::SELECT, self::RADIO, self::CHECKBOXES];

    /**
     * @param  array<string, string>  $label
     * @param  array<string, string>  $help
     * @param  array<string, list<string>>  $choices
     */
    public function __construct(
        public string $key,
        public string $type,
        public bool $required,
        public array $label,
        public array $help = [],
        public array $choices = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): ?self
    {
        $key = (string) ($data['key'] ?? '');
        $type = (string) ($data['type'] ?? '');
        if (preg_match('/^[a-z0-9]{6,16}$/', $key) !== 1 || ! in_array($type, self::TYPES, true)) {
            return null;
        }

        $label = [];
        $help = [];
        $choices = [];
        foreach (Locales::all() as $locale) {
            $label[$locale] = trim((string) ($data['label'][$locale] ?? ''));
            $help[$locale] = trim((string) ($data['help'][$locale] ?? ''));
            $lines = $data['choices'][$locale] ?? '';
            $lines = is_array($lines) ? $lines : preg_split('/\R/', (string) $lines);
            $choices[$locale] = array_values(array_filter(array_map(fn (mixed $line): string => trim((string) $line), (array) $lines), fn (string $line): bool => $line !== ''));
        }

        return new self($key, $type, (bool) ($data['required'] ?? false), array_filter($label), array_filter($help), array_filter($choices));
    }

    /** The label in this language, its fallbacks, or any language. */
    public function labelFor(string $locale): string
    {
        return self::pick($this->label, $locale) ?? $this->key;
    }

    public function helpFor(string $locale): ?string
    {
        return self::pick($this->help, $locale);
    }

    /**
     * The choices in this language; positions missing there come from the
     * fallback languages, so every language offers the same choices.
     *
     * @return list<string>
     */
    public function choicesFor(string $locale): array
    {
        $count = max([0, ...array_map('count', $this->choices)]);
        $out = [];
        for ($i = 0; $i < $count; $i++) {
            $byLocale = array_map(fn (array $list): string => $list[$i] ?? '', $this->choices);
            $out[] = self::pick(array_filter($byLocale), $locale) ?? (string) ($i + 1);
        }

        return $out;
    }

    public function hasChoices(): bool
    {
        return in_array($this->type, self::WITH_CHOICES, true);
    }

    /**
     * @param  array<string, string>  $values
     */
    private static function pick(array $values, string $locale): ?string
    {
        foreach ([...Locales::fallbackChain($locale), 'am', 'en', ...Locales::all()] as $candidate) {
            if (($values[$candidate] ?? '') !== '') {
                return $values[$candidate];
            }
        }

        return null;
    }
}
