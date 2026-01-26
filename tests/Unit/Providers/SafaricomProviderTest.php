<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Tests\Unit\Providers;

use Illuminate\Http\Request;
use Moffhub\Ussd\Providers\SafaricomProvider;
use Moffhub\Ussd\Tests\TestCase;
use Moffhub\Ussd\UssdResponse;

class SafaricomProviderTest extends TestCase
{
    private SafaricomProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new SafaricomProvider;
    }

    public function test_get_name_returns_safaricom(): void
    {
        $this->assertEquals('safaricom', $this->provider->getName());
    }

    public function test_get_phone_number_extracts_correctly(): void
    {
        $request = Request::create('/', 'POST', [
            'phoneNumber' => '+254712345678',
        ]);

        $this->assertEquals('+254712345678', $this->provider->getPhoneNumber($request));
    }

    public function test_get_phone_number_formats_local_number(): void
    {
        $request = Request::create('/', 'POST', [
            'phoneNumber' => '0712345678',
        ]);

        $this->assertEquals('+254712345678', $this->provider->getPhoneNumber($request));
    }

    public function test_get_user_input_extracts_text(): void
    {
        $request = Request::create('/', 'POST', [
            'text' => '1',
        ]);

        $this->assertEquals('1', $this->provider->getUserInput($request));
    }

    public function test_get_user_input_extracts_last_input_from_history(): void
    {
        $request = Request::create('/', 'POST', [
            'text' => '1*2*3',
        ]);

        $this->assertEquals('3', $this->provider->getUserInput($request));
    }

    public function test_get_input_history_returns_all_inputs(): void
    {
        $request = Request::create('/', 'POST', [
            'text' => '1*2*3',
        ]);

        $this->assertEquals(['1', '2', '3'], $this->provider->getInputHistory($request));
    }

    public function test_get_input_history_returns_empty_for_no_input(): void
    {
        $request = Request::create('/', 'POST', [
            'text' => '',
        ]);

        $this->assertEquals([], $this->provider->getInputHistory($request));
    }

    public function test_get_session_id_extracts_correctly(): void
    {
        $request = Request::create('/', 'POST', [
            'sessionId' => 'ATSessionId_123',
        ]);

        $this->assertEquals('ATSessionId_123', $this->provider->getSessionId($request));
    }

    public function test_get_service_code_extracts_correctly(): void
    {
        $request = Request::create('/', 'POST', [
            'serviceCode' => '*123#',
        ]);

        $this->assertEquals('*123#', $this->provider->getServiceCode($request));
    }

    public function test_get_network_code_extracts_correctly(): void
    {
        $request = Request::create('/', 'POST', [
            'networkCode' => '63902',
        ]);

        $this->assertEquals('63902', $this->provider->getNetworkCode($request));
    }

    public function test_format_response_adds_con_prefix(): void
    {
        $response = UssdResponse::continue('Hello');

        $this->assertEquals('CON Hello', $this->provider->formatResponse($response));
    }

    public function test_format_response_adds_end_prefix(): void
    {
        $response = UssdResponse::end('Goodbye');

        $this->assertEquals('END Goodbye', $this->provider->formatResponse($response));
    }

    public function test_format_response_truncates_long_messages(): void
    {
        $longMessage = str_repeat('A', 200);
        $response = UssdResponse::continue($longMessage);
        $formatted = $this->provider->formatResponse($response);

        // CON + space + message should be truncated
        $this->assertLessThanOrEqual(186, strlen($formatted)); // CON + space + 182
    }

    public function test_validate_request_requires_phone_and_session(): void
    {
        $validRequest = Request::create('/', 'POST', [
            'phoneNumber' => '+254712345678',
            'sessionId' => 'session123',
        ]);

        $invalidRequest = Request::create('/', 'POST', [
            'phoneNumber' => '+254712345678',
        ]);

        $this->assertTrue($this->provider->validateRequest($validRequest));
        $this->assertFalse($this->provider->validateRequest($invalidRequest));
    }

    public function test_supports_stateful_sessions(): void
    {
        $this->assertTrue($this->provider->supportsStatefulSessions());
    }

    public function test_get_max_message_length(): void
    {
        $this->assertEquals(182, $this->provider->getMaxMessageLength());
    }

    public function test_get_character_encoding(): void
    {
        $this->assertEquals('UTF-8', $this->provider->getCharacterEncoding());
    }

    public function test_get_response_headers(): void
    {
        $headers = $this->provider->getResponseHeaders();

        $this->assertArrayHasKey('Content-Type', $headers);
        $this->assertStringContainsString('text/plain', $headers['Content-Type']);
    }

    public function test_normalize_request(): void
    {
        $request = Request::create('/', 'POST', [
            'phoneNumber' => '+254712345678',
            'text' => '1*2',
            'sessionId' => 'session123',
            'serviceCode' => '*123#',
        ]);

        $normalized = $this->provider->normalizeRequest($request);

        $this->assertEquals('+254712345678', $normalized['phone_number']);
        $this->assertEquals('2', $normalized['input']);
        $this->assertEquals('session123', $normalized['session_id']);
        $this->assertEquals('*123#', $normalized['service_code']);
    }
}
