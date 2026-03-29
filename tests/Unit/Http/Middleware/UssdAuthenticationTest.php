<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Tests\Unit\Http\Middleware;

use Illuminate\Http\Request;
use Moffhub\Ussd\Http\Middleware\UssdAuthentication;
use Moffhub\Ussd\Tests\TestCase;

class UssdAuthenticationTest extends TestCase
{
    private UssdAuthentication $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new UssdAuthentication;
    }

    public function test_passes_when_authentication_disabled(): void
    {
        config(['ussd.gateway_authentication.enabled' => false]);

        $request = Request::create('/ussd', 'POST');
        $response = $this->middleware->handle($request, fn () => response('OK'));

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_blocks_unknown_ip(): void
    {
        config([
            'ussd.gateway_authentication.enabled' => true,
            'ussd.gateway_authentication.allowed_ips' => ['10.0.0.1', '10.0.0.2'],
        ]);

        $request = Request::create('/ussd', 'POST', [], [], [], [
            'REMOTE_ADDR' => '192.168.1.1',
        ]);

        $response = $this->middleware->handle($request, fn () => response('OK'));

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_allows_known_ip(): void
    {
        config([
            'ussd.gateway_authentication.enabled' => true,
            'ussd.gateway_authentication.allowed_ips' => ['127.0.0.1'],
        ]);

        $request = Request::create('/ussd', 'POST', [], [], [], [
            'REMOTE_ADDR' => '127.0.0.1',
        ]);

        $response = $this->middleware->handle($request, fn () => response('OK'));

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_blocks_missing_signature(): void
    {
        config([
            'ussd.gateway_authentication.enabled' => true,
            'ussd.gateway_authentication.allowed_ips' => [],
            'ussd.gateway_authentication.signature_header' => 'X-Gateway-Signature',
            'ussd.gateway_authentication.signature_secret' => 'test-secret',
        ]);

        $request = Request::create('/ussd', 'POST');

        $response = $this->middleware->handle($request, fn () => response('OK'));

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_blocks_invalid_signature(): void
    {
        config([
            'ussd.gateway_authentication.enabled' => true,
            'ussd.gateway_authentication.allowed_ips' => [],
            'ussd.gateway_authentication.signature_header' => 'X-Gateway-Signature',
            'ussd.gateway_authentication.signature_secret' => 'test-secret',
        ]);

        $request = Request::create('/ussd', 'POST', [], [], [], [], 'some payload');
        $request->headers->set('X-Gateway-Signature', 'invalid-signature');

        $response = $this->middleware->handle($request, fn () => response('OK'));

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_allows_valid_signature(): void
    {
        $secret = 'test-secret';
        $payload = '{"phoneNumber": "+254712345678"}';

        config([
            'ussd.gateway_authentication.enabled' => true,
            'ussd.gateway_authentication.allowed_ips' => [],
            'ussd.gateway_authentication.signature_header' => 'X-Gateway-Signature',
            'ussd.gateway_authentication.signature_secret' => $secret,
        ]);

        $signature = hash_hmac('sha256', $payload, $secret);

        $request = Request::create('/ussd', 'POST', [], [], [], [], $payload);
        $request->headers->set('X-Gateway-Signature', $signature);

        $response = $this->middleware->handle($request, fn () => response('OK'));

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_passes_when_no_ip_restriction_and_no_signature(): void
    {
        config([
            'ussd.gateway_authentication.enabled' => true,
            'ussd.gateway_authentication.allowed_ips' => [],
            'ussd.gateway_authentication.signature_header' => null,
            'ussd.gateway_authentication.signature_secret' => null,
        ]);

        $request = Request::create('/ussd', 'POST');

        $response = $this->middleware->handle($request, fn () => response('OK'));

        $this->assertEquals(200, $response->getStatusCode());
    }
}
