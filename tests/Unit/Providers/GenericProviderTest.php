<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Tests\Unit\Providers;

use Illuminate\Http\Request;
use Moffhub\Ussd\Providers\GenericProvider;
use Moffhub\Ussd\Tests\TestCase;
use Moffhub\Ussd\UssdResponse;

class GenericProviderTest extends TestCase
{
    private GenericProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new GenericProvider;
    }

    // ==================== Basic properties ====================

    public function test_get_name_returns_generic(): void
    {
        $this->assertEquals('generic', $this->provider->getName());
    }

    public function test_supports_stateful_sessions(): void
    {
        $this->assertTrue($this->provider->supportsStatefulSessions());
    }

    public function test_get_max_message_length(): void
    {
        $this->assertEquals(160, $this->provider->getMaxMessageLength());
    }

    // ==================== Auto-detection / field mapping ====================

    public function test_get_phone_number_from_phone_number_field(): void
    {
        $request = Request::create('/', 'POST', [
            'phoneNumber' => '+254712345678',
        ]);

        $this->assertEquals('+254712345678', $this->provider->getPhoneNumber($request));
    }

    public function test_get_phone_number_from_msisdn(): void
    {
        $request = Request::create('/', 'POST', [
            'msisdn' => '+254712345678',
        ]);

        $this->assertEquals('+254712345678', $this->provider->getPhoneNumber($request));
    }

    public function test_get_phone_number_from_phone_field(): void
    {
        $request = Request::create('/', 'POST', [
            'phone' => '+254712345678',
        ]);

        $this->assertEquals('+254712345678', $this->provider->getPhoneNumber($request));
    }

    public function test_get_phone_number_from_mobile_field(): void
    {
        $request = Request::create('/', 'POST', [
            'mobile' => '+254712345678',
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

    // ==================== User input auto-detection ====================

    public function test_get_user_input_from_text_field(): void
    {
        $request = Request::create('/', 'POST', [
            'text' => '1',
        ]);

        $this->assertEquals('1', $this->provider->getUserInput($request));
    }

    public function test_get_user_input_from_input_field(): void
    {
        $request = Request::create('/', 'POST', [
            'input' => '2',
        ]);

        $this->assertEquals('2', $this->provider->getUserInput($request));
    }

    public function test_get_user_input_from_user_input_field(): void
    {
        $request = Request::create('/', 'POST', [
            'userInput' => '3',
        ]);

        $this->assertEquals('3', $this->provider->getUserInput($request));
    }

    public function test_get_user_input_from_user_answer_field(): void
    {
        $request = Request::create('/', 'POST', [
            'UserAnswer' => '4',
        ]);

        $this->assertEquals('4', $this->provider->getUserInput($request));
    }

    public function test_get_user_input_from_message_field(): void
    {
        $request = Request::create('/', 'POST', [
            'message' => '5',
        ]);

        $this->assertEquals('5', $this->provider->getUserInput($request));
    }

    public function test_get_user_input_returns_empty_when_missing(): void
    {
        $request = Request::create('/', 'POST', []);

        $this->assertEquals('', $this->provider->getUserInput($request));
    }

    // ==================== Session ID auto-detection ====================

    public function test_get_session_id_from_session_id(): void
    {
        $request = Request::create('/', 'POST', [
            'sessionId' => 'sess_123',
        ]);

        $this->assertEquals('sess_123', $this->provider->getSessionId($request));
    }

    public function test_get_session_id_from_session_underscore_id(): void
    {
        $request = Request::create('/', 'POST', [
            'session_id' => 'sess_456',
        ]);

        $this->assertEquals('sess_456', $this->provider->getSessionId($request));
    }

    public function test_get_session_id_from_transaction_id(): void
    {
        $request = Request::create('/', 'POST', [
            'transactionId' => 'txn_789',
        ]);

        $this->assertEquals('txn_789', $this->provider->getSessionId($request));
    }

    public function test_get_session_id_returns_null_when_missing(): void
    {
        $request = Request::create('/', 'POST', []);

        $this->assertNull($this->provider->getSessionId($request));
    }

    // ==================== Service code auto-detection ====================

    public function test_get_service_code_from_service_code(): void
    {
        $request = Request::create('/', 'POST', [
            'serviceCode' => '*123#',
        ]);

        $this->assertEquals('*123#', $this->provider->getServiceCode($request));
    }

    public function test_get_service_code_from_short_code(): void
    {
        $request = Request::create('/', 'POST', [
            'shortCode' => '*456#',
        ]);

        $this->assertEquals('*456#', $this->provider->getServiceCode($request));
    }

    public function test_get_service_code_from_ussd_string(): void
    {
        $request = Request::create('/', 'POST', [
            'ussdString' => '*789#',
        ]);

        $this->assertEquals('*789#', $this->provider->getServiceCode($request));
    }

    public function test_get_service_code_returns_null_when_missing(): void
    {
        $request = Request::create('/', 'POST', []);

        $this->assertNull($this->provider->getServiceCode($request));
    }

    // ==================== Custom field mappings ====================

    public function test_set_field_mappings_changes_detection(): void
    {
        $this->provider->setFieldMappings([
            'phone' => 'customPhone',
        ]);

        $request = Request::create('/', 'POST', [
            'customPhone' => '+254712345678',
        ]);

        $this->assertEquals('+254712345678', $this->provider->getPhoneNumber($request));
    }

    public function test_set_field_mappings_returns_self(): void
    {
        $result = $this->provider->setFieldMappings(['phone' => 'custom']);

        $this->assertSame($this->provider, $result);
    }

    // ==================== Configuration ====================

    public function test_set_country_code(): void
    {
        $this->provider->setCountryCode('256');
        $request = Request::create('/', 'POST', [
            'phoneNumber' => '0712345678',
        ]);

        $this->assertStringStartsWith('+256', $this->provider->getPhoneNumber($request));
    }

    public function test_set_max_message_length(): void
    {
        $this->provider->setMaxMessageLength(200);

        $this->assertEquals(200, $this->provider->getMaxMessageLength());
    }

    // ==================== Fallback behavior ====================

    public function test_fallback_to_default_response_format(): void
    {
        $response = UssdResponse::continue('Hello');
        $formatted = $this->provider->formatResponse($response);

        $this->assertEquals('CON Hello', $formatted);
    }

    public function test_normalize_request_with_various_fields(): void
    {
        $request = Request::create('/', 'POST', [
            'mobile' => '+254712345678',
            'message' => '1',
            'transaction_id' => 'txn_123',
            'short_code' => '*123#',
        ]);

        $normalized = $this->provider->normalizeRequest($request);

        $this->assertEquals('+254712345678', $normalized['phone_number']);
        $this->assertEquals('1', $normalized['input']);
        $this->assertEquals('txn_123', $normalized['session_id']);
        $this->assertEquals('*123#', $normalized['service_code']);
    }
}
