<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Tests\Unit\Security;

use Illuminate\Http\Request;
use Moffhub\Ussd\Security\RequestVerifier;
use Moffhub\Ussd\Tests\TestCase;

class RequestVerifierTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('ussd.verify_signatures', true);
        $app['config']->set('ussd.providers', [
            'safaricom' => ['secret' => 'safaricom-api-key-123'],
            'airtel' => ['secret' => 'airtel-callback-token-456'],
            'mtn' => ['secret' => 'mtn-hmac-secret-789'],
        ]);
    }

    public function test_verify_returns_true_when_disabled(): void
    {
        config()->set('ussd.verify_signatures', false);
        $verifier = new RequestVerifier;

        $request = Request::create('/ussd', 'POST', ['phoneNumber' => '+254712345678']);

        $this->assertTrue($verifier->verify($request, 'safaricom'));
    }

    public function test_verify_safaricom_with_valid_api_key(): void
    {
        $verifier = new RequestVerifier;

        $request = Request::create('/ussd', 'POST', ['phoneNumber' => '+254712345678']);
        $request->headers->set('X-API-Key', 'safaricom-api-key-123');

        $this->assertTrue($verifier->verify($request, 'safaricom'));
    }

    public function test_verify_safaricom_with_invalid_api_key(): void
    {
        $verifier = new RequestVerifier;

        $request = Request::create('/ussd', 'POST', ['phoneNumber' => '+254712345678']);
        $request->headers->set('X-API-Key', 'wrong-key');

        $this->assertFalse($verifier->verify($request, 'safaricom'));
    }

    public function test_verify_safaricom_with_missing_api_key(): void
    {
        $verifier = new RequestVerifier;

        $request = Request::create('/ussd', 'POST', ['phoneNumber' => '+254712345678']);

        $this->assertFalse($verifier->verify($request, 'safaricom'));
    }

    public function test_verify_airtel_with_valid_token(): void
    {
        $verifier = new RequestVerifier;

        $request = Request::create('/ussd', 'POST', ['msisdn' => '+254712345678']);
        $request->headers->set('X-Callback-Token', 'airtel-callback-token-456');

        $this->assertTrue($verifier->verify($request, 'airtel'));
    }

    public function test_verify_airtel_with_bearer_token(): void
    {
        $verifier = new RequestVerifier;

        $request = Request::create('/ussd', 'POST', ['msisdn' => '+254712345678']);
        $request->headers->set('Authorization', 'Bearer airtel-callback-token-456');

        $this->assertTrue($verifier->verify($request, 'airtel'));
    }

    public function test_verify_airtel_with_invalid_token(): void
    {
        $verifier = new RequestVerifier;

        $request = Request::create('/ussd', 'POST', ['msisdn' => '+254712345678']);
        $request->headers->set('X-Callback-Token', 'wrong-token');

        $this->assertFalse($verifier->verify($request, 'airtel'));
    }

    public function test_verify_mtn_with_valid_hmac_signature(): void
    {
        $verifier = new RequestVerifier;

        $payload = json_encode(['msisdn' => '+234712345678', 'UserAnswer' => '1']) ?: '';
        $signature = hash_hmac('sha256', $payload, 'mtn-hmac-secret-789');

        $request = Request::create('/ussd', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], $payload);
        $request->headers->set('X-Signature', $signature);

        $this->assertTrue($verifier->verify($request, 'mtn'));
    }

    public function test_verify_mtn_with_invalid_signature(): void
    {
        $verifier = new RequestVerifier;

        $payload = json_encode(['msisdn' => '+234712345678']) ?: '';

        $request = Request::create('/ussd', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], $payload);
        $request->headers->set('X-Signature', 'invalid-signature');

        $this->assertFalse($verifier->verify($request, 'mtn'));
    }

    public function test_verify_mtn_with_missing_signature(): void
    {
        $verifier = new RequestVerifier;

        $request = Request::create('/ussd', 'POST', ['msisdn' => '+234712345678']);

        $this->assertFalse($verifier->verify($request, 'mtn'));
    }

    public function test_verify_returns_false_for_provider_without_secret(): void
    {
        $verifier = new RequestVerifier;

        $request = Request::create('/ussd', 'POST');

        $this->assertFalse($verifier->verify($request, 'unknown_provider'));
    }

    public function test_is_enabled(): void
    {
        $verifier = new RequestVerifier;

        $this->assertTrue($verifier->isEnabled());
    }

    public function test_is_not_enabled_when_disabled(): void
    {
        config()->set('ussd.verify_signatures', false);
        $verifier = new RequestVerifier;

        $this->assertFalse($verifier->isEnabled());
    }
}
