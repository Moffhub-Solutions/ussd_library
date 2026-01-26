<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Providers;

use Illuminate\Http\Request;

/**
 * Generic USSD Provider Adapter.
 *
 * A flexible provider that attempts to extract data from common field names.
 * Use this as a fallback when the specific provider is unknown or for
 * custom/aggregator gateways.
 *
 * Attempts to read from multiple common field names:
 * - Phone: phoneNumber, msisdn, phone, MSISDN
 * - Input: text, input, userInput, UserAnswer, message
 * - Session: sessionId, session_id, transactionId
 * - Service: serviceCode, shortCode, ussdString
 */
class GenericProvider extends AbstractUssdProvider
{
    protected string $name = 'generic';

    protected int $maxMessageLength = 160;

    protected string $characterEncoding = 'UTF-8';

    protected bool $supportsStatefulSessions = true;

    protected string $countryCode = '254';

    /** @var array<string, string> */
    protected array $fieldMappings = [
        'phone' => 'phoneNumber',
        'input' => 'text',
        'session' => 'sessionId',
        'service' => 'serviceCode',
    ];

    /**
     * Set custom field mappings.
     *
     * @param  array<string, string>  $mappings
     */
    public function setFieldMappings(array $mappings): self
    {
        $this->fieldMappings = array_merge($this->fieldMappings, $mappings);

        return $this;
    }

    /**
     * Set the country code for phone number formatting.
     */
    public function setCountryCode(string $countryCode): self
    {
        $this->countryCode = $countryCode;

        return $this;
    }

    /**
     * Set the maximum message length.
     */
    public function setMaxMessageLength(int $length): self
    {
        $this->maxMessageLength = $length;

        return $this;
    }

    public function getPhoneNumber(Request $request): string
    {
        $phoneFields = [
            $this->fieldMappings['phone'],
            'phoneNumber',
            'msisdn',
            'MSISDN',
            'phone',
            'mobile',
            'subscriberNumber',
        ];

        $phoneNumber = $this->getFirstAvailable($request, $phoneFields);

        return $this->formatPhoneNumber($phoneNumber, $this->countryCode);
    }

    public function getUserInput(Request $request): string
    {
        $inputFields = [
            $this->fieldMappings['input'],
            'text',
            'input',
            'userInput',
            'UserAnswer',
            'USER_ANSWER',
            'message',
            'Message',
        ];

        return $this->getFirstAvailable($request, $inputFields);
    }

    public function getSessionId(Request $request): ?string
    {
        $sessionFields = [
            $this->fieldMappings['session'],
            'sessionId',
            'session_id',
            'SessionId',
            'SESSION_ID',
            'transactionId',
            'transaction_id',
        ];

        $sessionId = $this->getFirstAvailable($request, $sessionFields);

        return $sessionId ?: null;
    }

    public function getServiceCode(Request $request): ?string
    {
        $serviceFields = [
            $this->fieldMappings['service'],
            'serviceCode',
            'service_code',
            'shortCode',
            'short_code',
            'ussdString',
            'ussd_string',
        ];

        $serviceCode = $this->getFirstAvailable($request, $serviceFields);

        return $serviceCode ?: null;
    }

    /**
     * Get the first available value from a list of field names.
     *
     * @param  array<int, string>  $fields
     */
    protected function getFirstAvailable(Request $request, array $fields): string
    {
        foreach ($fields as $field) {
            $value = $request->input($field);
            if (! empty($value)) {
                return (string) $value;
            }
        }

        return '';
    }
}
