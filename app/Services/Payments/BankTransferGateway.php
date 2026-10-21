<?php

namespace App\Services\Payments;

use App\Domain\Donations\Models\Donation;

/**
 * The donor transfers the amount to one of the party's accounts with the
 * pledge's reference; staff record the payment when the bank shows it.
 */
final class BankTransferGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'bank_transfer';
    }

    public function isOnline(): bool
    {
        return false;
    }

    public function start(Donation $donation): ?string
    {
        return null;
    }
}
