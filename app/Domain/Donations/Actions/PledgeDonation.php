<?php

namespace App\Domain\Donations\Actions;

use App\Domain\Donations\Enums\DonationStatus;
use App\Domain\Donations\Models\Donation;
use App\Domain\Events\Models\EventRegistration;
use App\Services\Payments\PaymentGateway;
use App\Services\Sms\SendSms;
use App\Settings\DonationSettings;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * A pledge from the donate page (ADR-0060): who gives, how much and,
 * optionally, for what. The donor gets a reference to write on the bank
 * transfer, on the page and by SMS when they gave a phone.
 */
final class PledgeDonation
{
    public const MAXIMUM = 10_000_000;

    public function __construct(
        private readonly DonationSettings $settings,
        private readonly PaymentGateway $gateway,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(array $input, string $locale): Donation
    {
        abort_unless($this->settings->enabled, 404);

        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:20', 'required_without:email'],
            'email' => ['nullable', 'email', 'max:190', 'required_without:phone'],
            'amount' => ['required', 'numeric', 'min:'.$this->settings->minimum, 'max:'.self::MAXIMUM],
            'purpose' => ['nullable', 'string', 'max:200'],
            'anonymous' => ['nullable', 'boolean'],
            'consent' => ['accepted'],
        ], [], [
            'name' => __('donations.field.name'),
            'phone' => __('donations.field.phone'),
            'email' => __('donations.field.email'),
            'amount' => __('donations.field.amount'),
            'purpose' => __('donations.field.purpose'),
        ])->validate();

        $phone = filled($data['phone'] ?? null) ? PhoneNumber::normalise((string) $data['phone']) : null;
        if (filled($data['phone'] ?? null) && $phone === null) {
            throw ValidationException::withMessages(['phone' => __('events.registration.validation.phone')]);
        }
        $email = filled($data['email'] ?? null) ? mb_strtolower(trim((string) $data['email'])) : null;

        $donation = Donation::query()->create([
            'reference' => Donation::newReference(),
            'locale' => $locale,
            'name' => trim((string) $data['name']),
            'phone' => $phone,
            'phone_hash' => $phone !== null ? EventRegistration::hash($phone) : null,
            'email' => $email,
            'email_hash' => $email !== null ? EventRegistration::hash($email) : null,
            'anonymous' => (bool) ($data['anonymous'] ?? false),
            'purpose' => filled($data['purpose'] ?? null) ? trim((string) $data['purpose']) : null,
            'amount' => number_format((float) $data['amount'], 2, '.', ''),
            'currency' => $this->settings->currency,
            'method' => $this->gateway->name(),
            'status' => DonationStatus::Pledged,
        ]);

        if ($phone !== null) {
            SendSms::dispatch($phone, __('donations.sms.pledged', [
                'reference' => $donation->reference,
                'amount' => self::money($donation->amount, $donation->currency),
            ], $locale));
        }

        return $donation;
    }

    public static function money(string|float|null $amount, string $currency): string
    {
        return number_format((float) $amount, 2).' '.$currency;
    }
}
