<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Moffhub\Ussd\Security\UssdRateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to apply per-phone rate limiting to USSD requests.
 *
 * Wraps the existing UssdRateLimiter service and applies it as HTTP middleware.
 */
class UssdRateLimit
{
    public function __construct(
        protected UssdRateLimiter $rateLimiter,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $phoneNumber = $this->extractPhoneNumber($request);

        if ($phoneNumber === '' || $phoneNumber === '0') {
            return $next($request);
        }

        if (! $this->rateLimiter->allow($phoneNumber)) {
            return response()->json([
                'error' => 'Too many requests. Please try again later.',
            ], 429);
        }

        return $next($request);
    }

    protected function extractPhoneNumber(Request $request): string
    {
        return (string) ($request->input('phoneNumber')
            ?? $request->input('msisdn')
            ?? $request->input('MSISDN')
            ?? '');
    }
}
