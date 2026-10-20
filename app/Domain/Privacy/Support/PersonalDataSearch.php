<?php

namespace App\Domain\Privacy\Support;

use App\Domain\Events\Models\EventRegistration;
use App\Domain\Forms\Models\FormSubmission;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Activitylog\Models\Activity;

/**
 * What the portal holds about a member of the public, found by their email
 * address or phone number through the keyed hashes, without decrypting every
 * record (ADR-0041): event registrations, messages and tickets with their
 * notes, and failed sign-ins that used the email. Staff accounts are only
 * reported; they are managed under Users.
 */
final class PersonalDataSearch
{
    public readonly ?string $email;

    public readonly ?string $phone;

    public function __construct(?string $email, ?string $phone)
    {
        $email = mb_strtolower(trim((string) $email));
        $this->email = $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : null;
        $this->phone = filled($phone) ? PhoneNumber::normalise((string) $phone) : null;
    }

    public function isValid(): bool
    {
        return $this->email !== null || $this->phone !== null;
    }

    /** A keyed hash of what was searched, for the request log. */
    public function subjectHash(): string
    {
        return EventRegistration::hash(($this->email ?? '').'|'.($this->phone ?? ''));
    }

    /**
     * @return Collection<int, EventRegistration>
     */
    public function registrations(): Collection
    {
        if (! $this->isValid()) {
            return new Collection;
        }

        return EventRegistration::query()
            ->whereNull('erased_at')
            ->where(fn (Builder $q) => $this->byHashes($q))
            ->with('event.translations')
            ->orderBy('created_at')
            ->get();
    }

    /**
     * @return Collection<int, FormSubmission>
     */
    public function submissions(): Collection
    {
        if (! $this->isValid()) {
            return new Collection;
        }

        return FormSubmission::query()
            ->whereNull('erased_at')
            ->where(fn (Builder $q) => $this->byHashes($q))
            ->with(['form.translations', 'notes'])
            ->orderBy('created_at')
            ->get();
    }

    /**
     * @return Collection<int, Activity>
     */
    public function failedSignIns(): Collection
    {
        if ($this->email === null) {
            return new Collection;
        }

        return Activity::query()->where('log_name', 'auth')->where('event', 'login_failed')
            ->where('properties->email', $this->email)->get();
    }

    public function staffAccount(): ?User
    {
        return $this->email === null ? null : User::query()->where('email', $this->email)->first();
    }

    /**
     * @return array{registrations: int, submissions: int, failed_sign_ins: int, staff_account: int}
     */
    public function counts(): array
    {
        return [
            'registrations' => $this->registrations()->count(),
            'submissions' => $this->submissions()->count(),
            'failed_sign_ins' => $this->failedSignIns()->count(),
            'staff_account' => $this->staffAccount() === null ? 0 : 1,
        ];
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     */
    private function byHashes(Builder $query): void
    {
        if ($this->email !== null) {
            $query->orWhere('email_hash', EventRegistration::hash($this->email));
        }
        if ($this->phone !== null) {
            $query->orWhere('phone_hash', EventRegistration::hash($this->phone));
        }
    }
}
