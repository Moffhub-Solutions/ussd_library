<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Providers;

use Illuminate\Http\Request;
use Moffhub\Ussd\Interfaces\UssdProviderInterface;
use Moffhub\Ussd\UssdResponse;

/**
 * Abstract base class for USSD provider adapters.
 *
 * Provides common functionality and default implementations
 * that can be overridden by specific provider implementations.
 */
abstract class AbstractUssdProvider implements UssdProviderInterface
{
    protected string $name = 'abstract';

    protected int $maxMessageLength = 182;

    protected string $characterEncoding = 'UTF-8';

    protected bool $supportsStatefulSessions = true;

    /** @var array<string, string> */
    protected array $responseHeaders = [
        'Content-Type' => 'text/plain; charset=utf-8',
    ];

    public function getName(): string
    {
        return $this->name;
    }

    public function supportsStatefulSessions(): bool
    {
        return $this->supportsStatefulSessions;
    }

    public function getMaxMessageLength(): int
    {
        return $this->maxMessageLength;
    }

    public function getCharacterEncoding(): string
    {
        return $this->characterEncoding;
    }

    public function getResponseHeaders(): array
    {
        return $this->responseHeaders;
    }

    public function formatResponse(UssdResponse $response): string
    {
        $prefix = $response->isContinue() ? 'CON ' : 'END ';
        $message = $this->truncateMessage($response->getMessage());

        return $prefix.$message;
    }

    public function normalizeRequest(Request $request): array
    {
        return [
            'phone_number' => $this->getPhoneNumber($request),
            'input' => $this->getUserInput($request),
            'session_id' => $this->getSessionId($request),
            'service_code' => $this->getServiceCode($request),
        ];
    }

    public function validateRequest(Request $request): bool
    {
        $phoneNumber = $this->getPhoneNumber($request);

        return ! empty($phoneNumber);
    }

    /**
     * Truncate message to fit within provider's character limit.
     */
    protected function truncateMessage(string $message): string
    {
        if (mb_strlen($message, $this->characterEncoding) <= $this->maxMessageLength) {
            return $message;
        }

        return mb_substr($message, 0, $this->maxMessageLength - 3, $this->characterEncoding).'...';
    }

    /**
     * Format phone number to E.164 format.
     */
    protected function formatPhoneNumber(string $phoneNumber, string $countryCode = '254'): string
    {
        $phoneNumber = preg_replace('/[^0-9+]/', '', $phoneNumber) ?? $phoneNumber;

        if (str_starts_with($phoneNumber, '+')) {
            return $phoneNumber;
        }

        if (str_starts_with($phoneNumber, '0')) {
            return '+'.$countryCode.substr($phoneNumber, 1);
        }

        if (str_starts_with($phoneNumber, $countryCode)) {
            return '+'.$phoneNumber;
        }

        return '+'.$countryCode.$phoneNumber;
    }
}
