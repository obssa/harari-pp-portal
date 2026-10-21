<?php

namespace App\Domain\Press\Actions;

use App\Domain\Press\Models\MediaContact;
use App\Domain\Translation\Support\Locales;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Validation\ValidationException;

/**
 * Adds or corrects a media contact (ADR-0054). One contact per email address.
 */
final class SaveMediaContact
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(MediaContact $contact, array $data, User $actor): MediaContact
    {
        abort_unless($actor->can('media_contacts.manage'), 403);

        $errors = [];
        $text = fn (string $field): string => trim((string) preg_replace('/\s+/u', ' ', (string) ($data[$field] ?? '')));
        $name = $text('name');
        if ($name === '' || mb_strlen($name) > 120) {
            $errors['data.name'] = __('membership.validation.name');
        }
        $outlet = $text('outlet');
        if ($outlet === '' || mb_strlen($outlet) > 120) {
            $errors['data.outlet'] = __('press.validation.outlet');
        }
        $position = $text('position');
        if (mb_strlen($position) > 120) {
            $errors['data.position'] = __('forms.validation.too_long', ['max' => 120]);
        }
        $email = mb_strtolower($text('email'));
        if ($email === '' || mb_strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['data.email'] = __('events.registration.validation.email');
        } elseif (MediaContact::query()->where('email_hash', MediaContact::hash($email))->whereKeyNot($contact->id)->exists()) {
            $errors['data.email'] = __('press.validation.duplicate');
        }
        $phone = null;
        if ($text('phone') !== '' && ($phone = PhoneNumber::normalise($text('phone'))) === null) {
            $errors['data.phone'] = __('events.registration.validation.phone');
        }
        // In the order given: the first published one is used.
        $languages = array_values(array_unique(array_filter(array_map('strval', (array) ($data['languages'] ?? [])), fn (string $l): bool => Locales::isSupported($l))));
        if ($languages === []) {
            $errors['data.languages'] = __('press.validation.languages');
        }
        $notes = trim((string) ($data['notes'] ?? ''));
        if (mb_strlen($notes) > 2000) {
            $errors['data.notes'] = __('forms.validation.too_long', ['max' => 2000]);
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $contact->fill([
            'name' => $name, 'outlet' => $outlet, 'position' => $position !== '' ? $position : null,
            'email' => $email, 'email_hash' => MediaContact::hash($email),
            'phone' => $phone, 'phone_hash' => $phone !== null ? MediaContact::hash($phone) : null,
            'languages' => $languages, 'notes' => $notes !== '' ? $notes : null,
        ]);
        if (! $contact->exists) {
            $contact->created_by = $actor->id;
        }
        $contact->save();

        return $contact;
    }
}
