<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Interfaces;

interface ValidatorInterface
{
    /**
     * Validate the input and return true if valid, or error message string if invalid
     *
     * @param  mixed  $input  The input to validate
     * @return bool|string True if valid, error message string if invalid
     */
    public function validate(mixed $input): bool|string;

    /**
     * Get the last error message from validation
     *
     * @return string|null The error message or null if no error
     */
    public function getErrorMessage(): ?string;
}
