<?php

namespace App\Services\Fayda;

use RuntimeException;

/**
 * eSignet could not be reached, or answered with something the portal does
 * not accept (wrong signature, issuer, audience or nonce).
 */
final class FaydaException extends RuntimeException {}
