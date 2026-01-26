<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Providers;

use Illuminate\Http\Request;

/**
 * Safaricom USSD Provider Adapter.
 *
 * Handles USSD requests from Safaricom/Africa's Talking gateway.
 * This is the most common format used in Kenya.
 *
 * Expected request format:
 * - phoneNumber: The user's phone number (e.g., +254712345678)
 * - text: The user's input, separated by * for multi-step
 * - sessionId: Unique session identifier
 * - serviceCode: The USSD shortcode (e.g., *123#)
 * - networkCode: Network code (optional)
 */
class SafaricomProvider extends AbstractUssdProvider
{
    protected string $name = 'safaricom';

    protected int $maxMessageLength = 182;

    protected string $characterEncoding = 'UTF-8';

    protected bool $supportsStatefulSessions = true;

    /** @var array<string, string> */
    protected array $responseHeaders = [
        'Content-Type' => 'text/plain; charset=utf-8',
    ];

    public function getPhoneNumber(Request $request): string
    {
        $phoneNumber = $request->input('phoneNumber') ?? '';

        return $this->formatPhoneNumber($phoneNumber, '254');
    }

    public function getUserInput(Request $request): string
    {
        $text = $request->input('text') ?? '';

        // Africa's Talking sends multiple inputs separated by *
        // We need the last input for processing
        if (str_contains((string) $text, '*')) {
            $parts = explode('*', (string) $text);

            return end($parts);
        }

        return $text;
    }

    /**
     * Get the full input history for session reconstruction.
     *
     * @return array<int, string>
     */
    public function getInputHistory(Request $request): array
    {
        $text = $request->input('text') ?? '';

        if (empty($text)) {
            return [];
        }

        return explode('*', (string) $text);
    }

    public function getSessionId(Request $request): ?string
    {
        return $request->input('sessionId');
    }

    public function getServiceCode(Request $request): ?string
    {
        return $request->input('serviceCode');
    }

    /**
     * Get the network code (carrier identifier).
     */
    public function getNetworkCode(Request $request): ?string
    {
        return $request->input('networkCode');
    }

    #[\Override]
    public function validateRequest(Request $request): bool
    {
        $phoneNumber = $request->input('phoneNumber');
        $sessionId = $request->input('sessionId');

        return ! empty($phoneNumber) && ! empty($sessionId);
    }
}
