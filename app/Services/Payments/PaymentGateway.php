<?php

namespace App\Services\Payments;

use App\Domain\Donations\Models\Donation;

/**
 * How a donor pays a pledge (ADR-0060). Bank transfer is the only driver
 * now; Telebirr or CBE Birr drivers implement this once the party has a
 * merchant account: `start()` returns the provider's payment page and the
 * provider's callback confirms the payment with RecordDonation::receive().
 */
interface PaymentGateway
{
    /** The value stored on the donation, e.g. "bank_transfer". */
    public function name(): string;

    /** Whether the donor is sent to a payment page right after pledging. */
    public function isOnline(): bool;

    /** The provider's payment page for this pledge; null for offline methods. */
    public function start(Donation $donation): ?string;
}
