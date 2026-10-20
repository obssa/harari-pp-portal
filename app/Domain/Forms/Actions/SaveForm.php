<?php

namespace App\Domain\Forms\Actions;

use App\Domain\Content\Actions\WriteTranslations;
use App\Domain\Forms\Enums\FormType;
use App\Domain\Forms\Models\Form;
use App\Domain\Forms\Support\FormField;
use App\Domain\Organisation\Models\Office;
use App\Domain\Translation\Support\Locales;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Saves a form with its languages and questions (ADR-0028). Each question
 * keeps a random key for life, so answers stay matched to their question
 * when questions are reordered or relabelled.
 */
final class SaveForm
{
    public function __construct(private readonly WriteTranslations $translations) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Form $form, array $data, User $actor): Form
    {
        /** @var array<string, array<string, mixed>> $translations */
        $translations = (array) ($data['translations'] ?? []);
        WriteTranslations::requireName($translations, 'title');

        $type = FormType::tryFrom((string) ($data['type'] ?? '')) ?? FormType::Contact;
        $email = WriteTranslations::email($data['notify_email'] ?? null);
        $fields = $this->fields((array) ($data['fields'] ?? []));

        return DB::transaction(function () use ($form, $data, $translations, $type, $email, $fields, $actor): Form {
            $form->fill([
                'type' => $type,
                'office_id' => filled($data['office_id'] ?? null) && Office::query()->whereKey($data['office_id'])->exists() ? (string) $data['office_id'] : null,
                'notify_email' => $email,
                'fields' => $fields,
                'anonymous_allowed' => (bool) ($data['anonymous_allowed'] ?? false),
                'response_days' => max(1, min(30, (int) ($data['response_days'] ?? 3))),
                'active' => (bool) ($data['active'] ?? true),
                'on_contact_page' => (bool) ($data['on_contact_page'] ?? false),
            ]);
            if (! $form->exists) {
                $form->created_by = $actor->id;
            }
            $form->updated_by = $actor->id;
            $form->save();

            // The contact page shows one form.
            if ($form->on_contact_page) {
                Form::query()->whereKeyNot($form->id)->where('on_contact_page', true)->update(['on_contact_page' => false]);
            }

            $this->translations->handle($form, $translations, ['intro', 'success_message', 'autoreply_subject', 'autoreply_body'], $actor->can('forms.manage'), 'title');

            return $form->refresh();
        });
    }

    /**
     * @param  array<mixed>  $rows
     * @return list<array<string, mixed>>
     */
    private function fields(array $rows): array
    {
        $fields = [];
        $seen = [];
        foreach (array_values($rows) as $position => $row) {
            if (! is_array($row)) {
                continue;
            }
            $key = preg_match('/^[a-z0-9]{6,16}$/', (string) ($row['key'] ?? '')) === 1 && ! isset($seen[$row['key']])
                ? (string) $row['key']
                : Str::lower(Str::random(10));
            $seen[$key] = true;

            $type = in_array($row['type'] ?? null, FormField::TYPES, true) ? (string) $row['type'] : FormField::TEXT;
            $label = [];
            $help = [];
            $choices = [];
            foreach (Locales::all() as $locale) {
                $label[$locale] = mb_substr(trim((string) ($row['label'][$locale] ?? '')), 0, 200);
                $help[$locale] = mb_substr(trim((string) ($row['help'][$locale] ?? '')), 0, 300);
                $choices[$locale] = mb_substr(trim((string) ($row['choices'][$locale] ?? '')), 0, 3000);
            }
            if (array_filter($label) === []) {
                throw ValidationException::withMessages(["data.fields.{$position}.label" => __('forms.validation.field_label')]);
            }
            if (in_array($type, FormField::WITH_CHOICES, true) && array_filter($choices) === []) {
                throw ValidationException::withMessages(["data.fields.{$position}.choices" => __('forms.validation.field_choices')]);
            }

            $fields[] = [
                'key' => $key,
                'type' => $type,
                'required' => (bool) ($row['required'] ?? false),
                'label' => $label,
                'help' => $help,
                'choices' => in_array($type, FormField::WITH_CHOICES, true) ? $choices : [],
            ];
        }

        if (count($fields) > Form::MAX_FIELDS) {
            throw ValidationException::withMessages(['data.fields' => __('forms.validation.too_many', ['max' => Form::MAX_FIELDS])]);
        }

        return $fields;
    }
}
