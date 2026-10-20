<?php

namespace App\Domain\Events\Actions;

use App\Domain\Events\Models\EventRegistration;

/**
 * The registration that was made, or the one that already existed.
 */
final readonly class RegistrationResult
{
    public function __construct(
        public EventRegistration $registration,
        public bool $existing,
    ) {}
}
