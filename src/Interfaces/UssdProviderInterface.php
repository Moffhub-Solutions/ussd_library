<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Interfaces;

use Illuminate\Http\Request;
use Moffhub\Ussd\UssdResponse;

/**
 * Interface for USSD provider adapters.
 *
 * This interface defines the contract for provider-specific request/response handling.
 * Different USSD providers (Safaricom, Airtel, MTN, etc.) have different field names
 * and response formats, and these adapters normalize them.
 */
interface UssdProviderInterface
{
    /**
     * Get the provider identifier name.
     */
    public function getName(): string;

    /**
     * Extract the phone number from the provider-specific request.
     */
    public function getPhoneNumber(Request $request): string;

    /**
     * Extract the user input from the provider-specific request.
     */
    public function getUserInput(Request $request): string;

    /**
     * Extract the session ID from the provider-specific request.
     */
    public function getSessionId(Request $request): ?string;

    /**
     * Extract the service code (USSD shortcode) from the request.
     */
    public function getServiceCode(Request $request): ?string;

    /**
     * Format the response according to provider specifications.
     */
    public function formatResponse(UssdResponse $response): string;

    /**
     * Check if this provider supports stateful sessions.
     */
    public function supportsStatefulSessions(): bool;

    /**
     * Get the maximum message length supported by this provider.
     */
    public function getMaxMessageLength(): int;

    /**
     * Get the character encoding used by this provider.
     */
    public function getCharacterEncoding(): string;

    /**
     * Validate that the request is from this provider.
     */
    public function validateRequest(Request $request): bool;

    /**
     * Get provider-specific HTTP headers for the response.
     *
     * @return array<string, string>
     */
    public function getResponseHeaders(): array;

    /**
     * Normalize the request to a standard format.
     *
     * @return array{phone_number: string, input: string, session_id: ?string, service_code: ?string}
     */
    public function normalizeRequest(Request $request): array;
}
