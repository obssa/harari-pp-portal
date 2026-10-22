<?php

namespace App\Domain\Membership\Actions;

use App\Domain\Access\Support\OfficeScope;
use App\Domain\Membership\Enums\ApplicationStatus;
use App\Domain\Membership\Mail\ApplicationCodeMail;
use App\Domain\Membership\Mail\ApplicationReceivedMail;
use App\Domain\Membership\Models\MembershipApplication;
use App\Domain\Membership\Notifications\NewApplicationNotice;
use App\Domain\Membership\Support\ApplicationOptions;
use App\Domain\Notifications\Support\Recipients;
use App\Domain\Organisation\Enums\OfficeType;
use App\Domain\Organisation\Models\Office;
use App\Models\User;
use App\Services\Calendar\EthiopianCalendar;
use App\Services\Sms\SendSms;
use App\Settings\MembershipSettings;
use App\Support\PhoneNumber;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * The membership application, step by step (PLAN.md §4, ADR-0044): about the
 * person, where they live and how to reach them, then consent and a code by
 * SMS that proves the phone is theirs — or, as Settings → Membership asks
 * (ADR-0067), a code by email or no code at all. Only then is the application
 * submitted. Whether a phone or ID number has applied before is never told
 * to the visitor; officers see possible duplicates.
 */
final class ApplyForMembership
{
    public const CODE_MINUTES = 10;

    public const CODE_ATTEMPTS = 5;

    public const CODES_PER_APPLICATION = 5;

    public const RESEND_SECONDS = 60;

    /**
     * Step 1: about the person. Creates the application on first save.
     *
     * @param  array<string, mixed>  $input
     */
    public function about(?MembershipApplication $application, array $input, string $locale): MembershipApplication
    {
        $errors = [];
        $name = trim((string) preg_replace('/\s+/u', ' ', (string) ($input['name'] ?? '')));
        if ($name === '' || mb_strlen($name) > 120) {
            $errors['name'] = __('membership.validation.name');
        }
        $sex = (string) ($input['sex'] ?? '');
        if (! in_array($sex, ApplicationOptions::SEXES, true)) {
            $errors['sex'] = __('membership.validation.sex');
        }
        $birth = $this->birthDate($input);
        if ($birth === null) {
            $errors['birth_date'] = __('membership.validation.birth_date');
        } elseif ($birth->diffInYears(CarbonImmutable::now()) < ApplicationOptions::MINIMUM_AGE) {
            $errors['birth_date'] = __('membership.validation.age', ['age' => ApplicationOptions::MINIMUM_AGE]);
        }
        $idType = (string) ($input['id_type'] ?? '');
        if (! in_array($idType, ApplicationOptions::ID_TYPES, true)) {
            $errors['id_type'] = __('membership.validation.id_type');
        }
        $idNumber = strtoupper((string) preg_replace('/[\s-]/', '', (string) ($input['id_number'] ?? '')));
        if ($idType !== 'none' && preg_match('/^[A-Z0-9]{4,30}$/', $idNumber) !== 1) {
            $errors['id_number'] = __('membership.validation.id_number');
        }
        $education = (string) ($input['education'] ?? '');
        if (! in_array($education, ApplicationOptions::EDUCATION, true)) {
            $errors['education'] = __('membership.validation.education');
        }
        $occupation = trim((string) ($input['occupation'] ?? ''));
        if (mb_strlen($occupation) > 120) {
            $errors['occupation'] = __('forms.validation.too_long', ['max' => 120]);
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $application ??= new MembershipApplication(['locale' => $locale]);
        $this->assertEditable($application);
        $hasId = $idType !== 'none' && $idNumber !== '';
        $application->fill([
            'locale' => $locale,
            'name' => $name,
            'sex' => $sex,
            'birth_date' => $birth?->toDateString(),
            'birth_year' => $birth?->year,
            'id_type' => $idType,
            'id_number' => $hasId ? $idNumber : null,
            'id_hash' => $hasId ? MembershipApplication::hash($idType.':'.$idNumber) : null,
            'education' => $education,
            'occupation' => $occupation !== '' ? $occupation : null,
        ]);
        if (! $application->exists) {
            $application->reference = MembershipApplication::newReference();
            $application->status = ApplicationStatus::Started;
        }
        $application->save();

        return $application;
    }

    /**
     * Step 2: where the person lives and how to reach them.
     *
     * @param  array<string, mixed>  $input
     */
    public function place(MembershipApplication $application, array $input): MembershipApplication
    {
        $this->assertEditable($application);
        $errors = [];

        $woreda = $this->office($input['woreda_id'] ?? null, OfficeType::Woreda);
        $kebele = $this->office($input['kebele_id'] ?? null, OfficeType::Kebele);
        $basic = $this->office($input['basic_org_id'] ?? null, OfficeType::BasicOrganisation);
        foreach (['woreda_id' => $woreda, 'kebele_id' => $kebele, 'basic_org_id' => $basic] as $field => $office) {
            if ($office === false) {
                $errors[$field] = __('membership.validation.office');
            }
        }
        $note = trim((string) ($input['address_note'] ?? ''));
        if (mb_strlen($note) > 200) {
            $errors['address_note'] = __('forms.validation.too_long', ['max' => 200]);
        }
        if ($woreda === null && $note === '') {
            $errors['woreda_id'] = __('membership.validation.place');
        }

        // ADR-0067: by email, the address is required and the phone optional; otherwise the phone is required.
        $byEmail = MembershipSettings::verification() === MembershipSettings::EMAIL;
        $typedPhone = trim((string) ($input['phone'] ?? ''));
        $phone = PhoneNumber::normalise($typedPhone);
        if ($phone === null && (! $byEmail || $typedPhone !== '')) {
            $errors['phone'] = __('events.registration.validation.phone');
        }
        $email = mb_strtolower(trim((string) ($input['email'] ?? '')));
        if (($email !== '' || $byEmail) && (mb_strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
            $errors['email'] = __('events.registration.validation.email');
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $contactChanged = $application->phone !== $phone || $application->email !== ($email !== '' ? $email : null);
        $application->fill([
            'woreda_id' => $woreda instanceof Office ? $woreda->id : null,
            'kebele_id' => $kebele instanceof Office ? $kebele->id : null,
            'basic_org_id' => $basic instanceof Office ? $basic->id : null,
            'address_note' => $note !== '' ? $note : null,
            'phone' => $phone,
            'phone_hash' => $phone !== null ? MembershipApplication::hash($phone) : null,
            'email' => $email !== '' ? $email : null,
            'email_hash' => $email !== '' ? MembershipApplication::hash($email) : null,
        ]);
        if ($contactChanged) {
            // A new number or address needs a new code.
            $application->forceFill(['code_hash' => null, 'code_expires_at' => null, 'code_attempts' => 0]);
        }
        $application->save();

        return $application;
    }

    /**
     * Step 3: consent, then a code by SMS to the phone or by email to the
     * address given, as Settings → Membership asks (ADR-0067).
     */
    public function sendCode(MembershipApplication $application, bool $consent): void
    {
        $this->assertEditable($application);
        $byEmail = MembershipSettings::verification() === MembershipSettings::EMAIL;
        if (MembershipSettings::verification() === MembershipSettings::NONE) {
            throw ValidationException::withMessages(['form' => __('membership.validation.no_code_needed')]);
        }
        if (! $consent) {
            throw ValidationException::withMessages(['consent' => __('events.registration.validation.consent')]);
        }
        if (($byEmail ? $application->email : $application->phone) === null || $application->name === null) {
            throw ValidationException::withMessages(['form' => __('membership.validation.incomplete')]);
        }
        if ($application->code_sent_at !== null && $application->code_sent_at->diffInSeconds(CarbonImmutable::now()) < self::RESEND_SECONDS) {
            throw ValidationException::withMessages(['code' => __('membership.validation.wait', ['seconds' => self::RESEND_SECONDS])]);
        }
        $perPhone = 'membership-code:'.($byEmail ? $application->email_hash : $application->phone_hash);
        if ($application->codes_sent >= self::CODES_PER_APPLICATION || RateLimiter::tooManyAttempts($perPhone, self::CODES_PER_APPLICATION)) {
            throw ValidationException::withMessages(['code' => __('membership.validation.too_many_codes')]);
        }

        $code = (string) random_int(100000, 999999);
        $application->forceFill([
            'consented_at' => $application->consented_at ?? CarbonImmutable::now(),
            'code_hash' => MembershipApplication::hash('code:'.$application->id.':'.$code),
            'code_expires_at' => CarbonImmutable::now()->addMinutes(self::CODE_MINUTES),
            'code_attempts' => 0,
            'codes_sent' => $application->codes_sent + 1,
            'code_sent_at' => CarbonImmutable::now(),
        ])->save();
        RateLimiter::hit($perPhone, 86400);

        // Sent at once, not queued: the person is waiting for it.
        if ($byEmail) {
            Mail::to((string) $application->email)->locale($application->locale)->send(new ApplicationCodeMail($code, self::CODE_MINUTES));
        } else {
            SendSms::dispatchSync((string) $application->phone, __('membership.sms.code', ['code' => $code, 'minutes' => self::CODE_MINUTES], $application->locale));
        }
    }

    /**
     * Step 3 when no code is asked for (ADR-0067): consent, and the
     * application is submitted at once.
     */
    public function submit(MembershipApplication $application, bool $consent): MembershipApplication
    {
        $this->assertEditable($application);
        if (MembershipSettings::verification() !== MembershipSettings::NONE) {
            throw ValidationException::withMessages(['code' => __('membership.validation.code')]);
        }
        if (! $consent) {
            throw ValidationException::withMessages(['consent' => __('events.registration.validation.consent')]);
        }
        if ($application->phone === null || $application->name === null) {
            throw ValidationException::withMessages(['form' => __('membership.validation.incomplete')]);
        }

        $now = CarbonImmutable::now();
        $application->forceFill([
            'consented_at' => $application->consented_at ?? $now,
            'submitted_at' => $now,
            'status' => ApplicationStatus::Submitted,
        ])->save();

        $this->confirm($application);
        $this->tellOfficers($application);

        return $application;
    }

    /**
     * Step 4: the code from the SMS; the application is then submitted.
     */
    public function verify(MembershipApplication $application, string $typed): MembershipApplication
    {
        $this->assertEditable($application);
        $code = (string) preg_replace('/\D/', '', $typed);
        $valid = $application->code_hash !== null
            && $application->code_expires_at !== null && $application->code_expires_at->isFuture()
            && $application->code_attempts < self::CODE_ATTEMPTS;

        if (! $valid || ! hash_equals((string) $application->code_hash, MembershipApplication::hash('code:'.$application->id.':'.$code))) {
            if ($valid) {
                $application->forceFill(['code_attempts' => $application->code_attempts + 1])->save();
            }

            throw ValidationException::withMessages(['code' => __('membership.validation.code')]);
        }

        $now = CarbonImmutable::now();
        $application->forceFill([
            'code_hash' => null,
            'code_expires_at' => null,
            MembershipSettings::verification() === MembershipSettings::EMAIL ? 'email_verified_at' : 'phone_verified_at' => $now,
            'submitted_at' => $now,
            'status' => ApplicationStatus::Submitted,
        ])->save();

        $this->confirm($application);
        $this->tellOfficers($application);

        return $application;
    }

    private function confirm(MembershipApplication $application): void
    {
        try {
            if (MembershipSettings::verification() === MembershipSettings::SMS && $application->phone !== null) {
                SendSms::dispatch($application->phone, __('membership.sms.received', ['reference' => $application->reference], $application->locale));
            } elseif ($application->email !== null) {
                Mail::to($application->email)->locale($application->locale)->queue(new ApplicationReceivedMail($application->reference, $application->locale));
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function tellOfficers(MembershipApplication $application): void
    {
        try {
            $officers = Recipients::withPermission('applications.view')
                ->filter(fn (User $user): bool => OfficeScope::covers($user, $application->woreda_id, 'applications.view'));
            Recipients::notify($officers, NewApplicationNotice::for($application));
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function assertEditable(MembershipApplication $application): void
    {
        if ($application->exists && $application->status !== ApplicationStatus::Started) {
            throw ValidationException::withMessages(['form' => __('membership.validation.already_sent')]);
        }
    }

    /**
     * The birth date as typed: day, month and year in the Ethiopian or the
     * Gregorian calendar.
     *
     * @param  array<string, mixed>  $input
     */
    private function birthDate(array $input): ?CarbonImmutable
    {
        $day = filter_var($input['birth_day'] ?? null, FILTER_VALIDATE_INT);
        $month = filter_var($input['birth_month'] ?? null, FILTER_VALIDATE_INT);
        $year = filter_var($input['birth_year'] ?? null, FILTER_VALIDATE_INT);
        if (! is_int($day) || ! is_int($month) || ! is_int($year) || $year < 1890 || $year > 2100) {
            return null;
        }

        try {
            $date = ($input['birth_calendar'] ?? 'ethiopian') === 'gregorian'
                ? (checkdate($month, $day, $year) ? CarbonImmutable::create($year, $month, $day, 0, 0, 0, EthiopianCalendar::timezone()) : null)
                : app(EthiopianCalendar::class)->toGregorian($year, $month, $day);
        } catch (Throwable) {
            return null;
        }

        return $date instanceof CarbonImmutable && $date->isPast() ? $date->startOfDay() : null;
    }

    /**
     * @return Office|false|null null when not chosen, false when not a published office of that type
     */
    private function office(mixed $id, OfficeType $type): Office|false|null
    {
        if (! is_string($id) || $id === '') {
            return null;
        }
        $office = Office::query()->whereKey($id)->where('type', $type->value)->first();

        return $office instanceof Office ? $office : false;
    }
}
