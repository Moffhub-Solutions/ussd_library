<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Interfaces;

/**
 * Contract for USSD rate limiting.
 *
 * Bind your own implementation to this interface in the container (or pass it
 * via UssdBuilder::rateLimiter()) to replace the default without forking.
 */
interface RateLimiterInterface
{
    /**
     * Whether a request from the given phone number is allowed right now.
     */
    public function allow(string $phoneNumber, string $action = 'request'): bool;
}
