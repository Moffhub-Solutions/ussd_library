<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Interfaces;

/**
 * Contract for USSD input sanitization/validation.
 *
 * Bind your own implementation to this interface in the container (or pass it
 * via UssdBuilder::inputSanitizer()) to replace the default without forking.
 */
interface InputSanitizerInterface
{
    /**
     * Sanitize and validate input for a given context.
     *
     * @return array{input: string, valid: bool, suspicious: bool, reasons: array<int, string>}
     */
    public function sanitize(string $input, string $context = 'general'): array;
}
