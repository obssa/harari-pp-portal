<?php

namespace App\Domain\Forms\Actions;

use App\Domain\Forms\Enums\SubmissionStatus;
use App\Domain\Forms\Mail\NewSubmissionMail;
use App\Domain\Forms\Mail\SubmissionReceivedMail;
use App\Domain\Forms\Models\Form;
use App\Domain\Forms\Models\FormSubmission;
use App\Domain\Forms\Support\FormField;
use App\Domain\Forms\Support\WorkingDays;
use App\Domain\Media\Enums\MediaKind;
use App\Domain\Media\Exceptions\RejectedUpload;
use App\Domain\Media\Support\UploadInspector;
use App\Support\PhoneNumber;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Turns a sent form into a ticket (ADR-0028): checks every answer against
 * its question, keeps attachments on the private disk, routes the ticket
 * to the form's office, sets the answer-by date, and sends the automatic
 * reply and the staff notice.
 */
final class SubmitForm
{
    /** Largest attachment a visitor may send. */
    public const MAX_FILE_BYTES = 5 * 1024 * 1024;

    public const FILE_DISK = 'local';

    public function __construct(private readonly UploadInspector $inspector) {}

    /**
     * @param  array<string, mixed>  $input  name, email, phone and answers keyed by question
     * @param  array<string, UploadedFile>  $uploads  keyed by question
     */
    public function handle(Form $form, array $input, array $uploads, string $locale): FormSubmission
    {
        $errors = [];
        $name = trim((string) preg_replace('/\s+/u', ' ', (string) ($input['name'] ?? '')));
        $email = mb_strtolower(trim((string) ($input['email'] ?? '')));
        $phoneInput = trim((string) ($input['phone'] ?? ''));
        $phone = PhoneNumber::normalise($phoneInput);

        if (mb_strlen($name) > 120 || ($name === '' && ! $form->anonymous_allowed)) {
            $errors['name'] = __('forms.validation.name');
        }
        if ($email !== '' && (mb_strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
            $errors['email'] = __('events.registration.validation.email');
        }
        if ($phoneInput !== '' && $phone === null) {
            $errors['phone'] = __('events.registration.validation.phone');
        }
        if ($email === '' && $phoneInput === '' && ! $form->anonymous_allowed) {
            $errors['email'] = __('forms.validation.contact');
        }

        $answers = [];
        foreach ($form->questions() as $field) {
            if ($field->type === FormField::FILE) {
                continue;
            }
            [$value, $error] = $this->answer($field, $input['answers'][$field->key] ?? null, $locale);
            if ($error !== null) {
                $errors["answers.{$field->key}"] = $error;
            } elseif ($value !== null) {
                $answers[$field->key] = $value;
            }
        }

        $checkedFiles = [];
        foreach ($form->questions() as $field) {
            if ($field->type !== FormField::FILE) {
                continue;
            }
            $upload = $uploads[$field->key] ?? null;
            if (! $upload instanceof UploadedFile) {
                if ($field->required) {
                    $errors["answers.{$field->key}"] = __('forms.validation.required');
                }

                continue;
            }
            $error = $this->checkFile($upload);
            if ($error !== null) {
                $errors["answers.{$field->key}"] = $error;
            } else {
                $checkedFiles[$field->key] = $upload;
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $submission = new FormSubmission([
            'form_id' => $form->id,
            'office_id' => $form->office_id,
            'locale' => $locale,
            'reference' => FormSubmission::newReference(),
            'name' => $name !== '' ? $name : null,
            'email' => $email !== '' ? $email : null,
            'phone' => $phone,
            'answers' => $answers,
            'status' => SubmissionStatus::New,
            'due_at' => WorkingDays::after(CarbonImmutable::now(), $form->response_days),
        ]);
        $submission->site_id = $form->site_id;
        $submission->save();

        $files = [];
        foreach ($checkedFiles as $key => $upload) {
            $inspected = $this->inspector->inspect((string) $upload->getRealPath(), (string) $upload->getClientOriginalName());
            $path = "form-submissions/{$submission->id}/".Str::random(24).'.'.$inspected->extension;
            Storage::disk(self::FILE_DISK)->put($path, (string) file_get_contents((string) $upload->getRealPath()));
            $files[] = [
                'field' => $key,
                'path' => $path,
                'name' => Str::limit(Str::ascii(pathinfo((string) $upload->getClientOriginalName(), PATHINFO_FILENAME)), 80, '').'.'.$inspected->extension,
                'size' => $inspected->size,
                'mime' => $inspected->mime,
            ];
        }
        if ($files !== []) {
            $submission->files = $files;
            $submission->save();
        }

        if ($submission->email !== null) {
            Mail::to($submission->email)->locale($locale)->queue(new SubmissionReceivedMail($submission));
        }
        foreach ($this->staffAddresses($form) as $address) {
            Mail::to($address)->queue(new NewSubmissionMail($submission));
        }

        return $submission;
    }

    /**
     * @return array{0: mixed, 1: ?string} the stored value and an error
     */
    private function answer(FormField $field, mixed $raw, string $locale): array
    {
        $text = is_scalar($raw) ? trim((string) $raw) : '';
        $empty = $field->type === FormField::CHECKBOXES ? ! is_array($raw) || array_filter($raw, 'is_scalar') === [] : $text === '';

        if ($empty) {
            return [null, $field->required ? __('forms.validation.required') : null];
        }

        return match ($field->type) {
            FormField::TEXT => mb_strlen($text) > 300 ? [null, __('forms.validation.too_long', ['max' => 300])] : [$text, null],
            FormField::TEXTAREA => mb_strlen($text) > 5000 ? [null, __('forms.validation.too_long', ['max' => 5000])] : [$text, null],
            FormField::EMAIL => filter_var($text, FILTER_VALIDATE_EMAIL) === false || mb_strlen($text) > 190 ? [null, __('events.registration.validation.email')] : [mb_strtolower($text), null],
            FormField::PHONE => ($phone = PhoneNumber::normalise($text)) === null ? [null, __('events.registration.validation.phone')] : [$phone, null],
            FormField::NUMBER => is_numeric($text) && abs((float) $text) < 1e12 ? [str_contains($text, '.') ? (float) $text : (int) $text, null] : [null, __('forms.validation.number')],
            FormField::DATE => $this->date($text),
            FormField::CONSENT => filter_var($raw, FILTER_VALIDATE_BOOLEAN) ? [true, null] : [null, $field->required ? __('forms.validation.required') : null],
            FormField::SELECT, FormField::RADIO => $this->choice($field, $text, $locale),
            FormField::CHECKBOXES => $this->choices($field, (array) $raw, $locale),
            default => [null, null],
        };
    }

    /**
     * Choices are kept by position, and as the words the person saw.
     *
     * @return array{0: mixed, 1: ?string}
     */
    private function choice(FormField $field, string $value, string $locale): array
    {
        $choices = $field->choicesFor($locale);
        $index = ctype_digit($value) ? (int) $value : -1;

        return isset($choices[$index]) ? [['index' => $index, 'text' => $choices[$index]], null] : [null, __('forms.validation.choice')];
    }

    /**
     * @param  array<mixed>  $values
     * @return array{0: mixed, 1: ?string}
     */
    private function choices(FormField $field, array $values, string $locale): array
    {
        $choices = $field->choicesFor($locale);
        $picked = [];
        foreach ($values as $value) {
            $index = is_scalar($value) && ctype_digit((string) $value) ? (int) $value : -1;
            if (! isset($choices[$index])) {
                return [null, __('forms.validation.choice')];
            }
            $picked[$index] = ['index' => $index, 'text' => $choices[$index]];
        }
        ksort($picked);

        return [array_values($picked), null];
    }

    /**
     * @return array{0: mixed, 1: ?string}
     */
    private function date(string $value): array
    {
        $date = CarbonImmutable::createFromFormat('Y-m-d', $value);

        return $date !== null && $date->format('Y-m-d') === $value ? [$value, null] : [null, __('organisation.validation.date')];
    }

    private function checkFile(UploadedFile $upload): ?string
    {
        if (! $upload->isValid() || $upload->getSize() > self::MAX_FILE_BYTES) {
            return __('forms.validation.file_size', ['max' => 5]);
        }

        try {
            $inspected = $this->inspector->inspect((string) $upload->getRealPath(), (string) $upload->getClientOriginalName());
        } catch (RejectedUpload) {
            return __('forms.validation.file_type');
        }

        $allowed = in_array($inspected->mime, ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.oasis.opendocument.text'], true)
            || in_array($inspected->mime, ['image/jpeg', 'image/png'], true);

        return $allowed && in_array($inspected->kind, [MediaKind::Document, MediaKind::Image], true) ? null : __('forms.validation.file_type');
    }

    /**
     * Who hears about a new ticket: the form's address, otherwise its office's.
     *
     * @return list<string>
     */
    private function staffAddresses(Form $form): array
    {
        $address = $form->notify_email ?: $form->office?->email;

        return filled($address) && filter_var($address, FILTER_VALIDATE_EMAIL) !== false ? [(string) $address] : [];
    }
}
