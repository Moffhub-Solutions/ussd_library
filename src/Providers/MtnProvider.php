<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Providers;

use Illuminate\Http\Request;
use Moffhub\Ussd\UssdResponse;

/**
 * MTN USSD Provider Adapter.
 *
 * Handles USSD requests from MTN networks across multiple African countries.
 * MTN has some unique field names and response requirements.
 *
 * Expected request format:
 * - msisdn or MSISDN: The user's phone number
 * - UserAnswer or userAnswer: The user's input
 * - sessionId or SessionId: Session identifier
 * - ussdString: The USSD shortcode/service code
 */
class MtnProvider extends AbstractUssdProvider
{
    protected string $name = 'mtn';

    protected int $maxMessageLength = 160;

    protected string $characterEncoding = 'UTF-8';

    protected bool $supportsStatefulSessions = true;

    protected string $countryCode = '234'; // Nigeria default

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
            ?? $request->input('MSISDN')
            ?? $request->input('phoneNumber')
            ?? $request->input('subscriberNumber')
            ?? '';

        return $this->formatPhoneNumber($phoneNumber, $this->countryCode);
    }

    public function getUserInput(Request $request): string
    {
        return $request->input('UserAnswer')
            ?? $request->input('userAnswer')
            ?? $request->input('USER_ANSWER')
            ?? $request->input('text')
            ?? $request->input('input')
            ?? '';
    }

    public function getSessionId(Request $request): ?string
    {
        return $request->input('sessionId')
            ?? $request->input('SessionId')
            ?? $request->input('SESSION_ID')
            ?? $request->input('transactionId');
    }

    public function getServiceCode(Request $request): ?string
    {
        return $request->input('ussdString')
            ?? $request->input('serviceCode')
            ?? $request->input('USSD_STRING')
            ?? $request->input('shortCode');
    }

    /**
     * Get MTN-specific request type.
     */
    public function getRequestType(Request $request): ?string
    {
        return $request->input('ussdOperation')
            ?? $request->input('requestType')
            ?? $request->input('USSD_OPERATION');
    }

    #[\Override]
    public function formatResponse(UssdResponse $response): string
    {
        // MTN sometimes requires different response format
        // depending on whether it's a continue or end response
        $message = $this->truncateMessage($response->getMessage());

        if ($response->isContinue()) {
            return 'CON '.$message;
        }

        return 'END '.$message;
    }

    #[\Override]
    public function validateRequest(Request $request): bool
    {
        $phoneNumber = $this->getPhoneNumber($request);
        $sessionId = $this->getSessionId($request);

        return $phoneNumber !== '' && $phoneNumber !== '0' && ! in_array($sessionId, [null, '', '0'], true);
    }
}
