<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Providers;

use Illuminate\Http\Request;

/**
 * Airtel USSD Provider Adapter.
 *
 * Handles USSD requests from Airtel networks across multiple countries.
 * Field names and formats may vary by country implementation.
 *
 * Expected request format:
 * - msisdn: The user's phone number
 * - input or text: The user's input
 * - transactionId or sessionId: Session/transaction identifier
 * - serviceCode: The USSD shortcode
 */
class AirtelProvider extends AbstractUssdProvider
{
    protected string $name = 'airtel';

    protected int $maxMessageLength = 160;

    protected string $characterEncoding = 'UTF-8';

    protected bool $supportsStatefulSessions = true;

    protected string $countryCode = '254';

    /** @var array<string, string> */
    protected array $responseHeaders = [
        'Content-Type' => 'text/plain; charset=utf-8',
    ];

    /**
     * Set the country code for phone number formatting.
     */
    public function setCountryCode(string $countryCode): self
    {
        $this->countryCode = $countryCode;

        return $this;
    }

    public function getPhoneNumber(Request $request): string
    {
        $phoneNumber = $request->input('msisdn')
            ?? $request->input('phoneNumber')
            ?? $request->input('MSISDN')
            ?? '';

        return $this->formatPhoneNumber($phoneNumber, $this->countryCode);
    }

    public function getUserInput(Request $request): string
    {
        return $request->input('input')
            ?? $request->input('text')
            ?? $request->input('INPUT')
            ?? $request->input('userInput')
            ?? '';
    }

    public function getSessionId(Request $request): ?string
    {
        return $request->input('transactionId')
            ?? $request->input('sessionId')
            ?? $request->input('TRANSACTION_ID')
            ?? $request->input('session_id');
    }

    public function getServiceCode(Request $request): ?string
    {
        return $request->input('serviceCode')
            ?? $request->input('shortCode')
            ?? $request->input('SERVICE_CODE');
    }

    public function validateRequest(Request $request): bool
    {
        $phoneNumber = $this->getPhoneNumber($request);

        return ! empty($phoneNumber);
    }
}
