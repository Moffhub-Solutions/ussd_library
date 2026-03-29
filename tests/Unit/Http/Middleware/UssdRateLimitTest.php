<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Tests\Unit\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Moffhub\Ussd\Http\Middleware\UssdRateLimit;
use Moffhub\Ussd\Security\UssdRateLimiter;
use Moffhub\Ussd\Tests\TestCase;

class UssdRateLimitTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_allows_request_within_limits(): void
    {
        $rateLimiter = new UssdRateLimiter([
            'max_requests_per_minute' => 10,
            'max_requests_per_hour' => 100,
            'max_requests_per_day' => 500,
            'enabled' => true,
        ]);

        $middleware = new UssdRateLimit($rateLimiter);

        $request = Request::create('/ussd', 'POST', [
            'phoneNumber' => '+254712345678',
        ]);

        $response = $middleware->handle($request, fn () => response('OK'));

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_blocks_request_when_rate_limit_exceeded(): void
    {
        $rateLimiter = new UssdRateLimiter([
            'max_requests_per_minute' => 2,
            'max_requests_per_hour' => 100,
            'max_requests_per_day' => 500,
            'enabled' => true,
        ]);

        $middleware = new UssdRateLimit($rateLimiter);

        $request = Request::create('/ussd', 'POST', [
            'phoneNumber' => '+254712345678',
        ]);

        // Exhaust the limit
        $middleware->handle($request, fn () => response('OK'));
        $middleware->handle($request, fn () => response('OK'));

        // Third request should be rate limited
        $response = $middleware->handle($request, fn () => response('OK'));

        $this->assertEquals(429, $response->getStatusCode());
    }

    public function test_passes_when_no_phone_number(): void
    {
        $rateLimiter = new UssdRateLimiter([
            'max_requests_per_minute' => 1,
            'enabled' => true,
        ]);

        $middleware = new UssdRateLimit($rateLimiter);

        $request = Request::create('/ussd', 'POST');

        $response = $middleware->handle($request, fn () => response('OK'));

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_extracts_phone_from_msisdn_field(): void
    {
        $rateLimiter = new UssdRateLimiter([
            'max_requests_per_minute' => 2,
            'max_requests_per_hour' => 100,
            'max_requests_per_day' => 500,
            'enabled' => true,
        ]);

        $middleware = new UssdRateLimit($rateLimiter);

        $request = Request::create('/ussd', 'POST', [
            'msisdn' => '+254712345678',
        ]);

        $response = $middleware->handle($request, fn () => response('OK'));

        $this->assertEquals(200, $response->getStatusCode());
    }
}
