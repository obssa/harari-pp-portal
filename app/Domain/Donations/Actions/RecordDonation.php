<?php

namespace App\Domain\Donations\Actions;

use App\Domain\Donations\Enums\DonationStatus;
use App\Domain\Donations\Models\Donation;
use App\Models\User;
use App\Services\Sms\SendSms;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * What staff do with a pledge (ADR-0060): record the payment when the bank
 * shows it, which gives the receipt its number, or cancel it with a reason.
 */
final class RecordDonation
{
    public function receive(Donation $donation, string|float $amount, string $receivedOn, ?string $paymentReference, User $actor): Donation
    {
        abort_unless($actor->can('donations.manage'), 403);
        if ($donation->status !== DonationStatus::Pledged) {
            throw ValidationException::withMessages(['status' => __('donations.validation.not_pledged')]);
        }
        if (! is_numeric($amount) || (float) $amount <= 0 || (float) $amount > PledgeDonation::MAXIMUM) {
            throw ValidationException::withMessages(['received_amount' => __('donations.validation.amount')]);
        }
        $day = CarbonImmutable::parse($receivedOn);
        if ($day->isFuture()) {
            throw ValidationException::withMessages(['received_on' => __('donations.validation.future')]);
        }

        DB::transaction(function () use ($donation, $amount, $day, $paymentReference, $actor): void {
            $donation->forceFill([
                'status' => DonationStatus::Received,
                'received_amount' => number_format((float) $amount, 2, '.', ''),
                'received_on' => $day->toDateString(),
                'payment_reference' => filled($paymentReference) ? mb_substr(trim((string) $paymentReference), 0, 100) : null,
                'receipt_no' => Donation::nextReceipt(),
                'recorded_by' => $actor->id,
            ])->save();
        });

        if ($donation->phone !== null && $donation->erased_at === null) {
            SendSms::dispatch($donation->phone, __('donations.sms.received', [
                'amount' => PledgeDonation::money($donation->received_amount, $donation->currency),
                'receipt' => $donation->receipt_no,
            ], $donation->locale));
        }

        return $donation;
    }

    public function cancel(Donation $donation, string $reason, User $actor): void
    {
        abort_unless($actor->can('donations.manage'), 403);
        if ($donation->status !== DonationStatus::Pledged) {
            throw ValidationException::withMessages(['status' => __('donations.validation.not_pledged')]);
        }
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 500) {
            throw ValidationException::withMessages(['cancel_reason' => __('membership.validation.reason')]);
        }

        $donation->forceFill(['status' => DonationStatus::Cancelled, 'cancel_reason' => $reason, 'recorded_by' => $actor->id])->save();
    }
}
