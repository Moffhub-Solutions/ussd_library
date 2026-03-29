<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to verify USSD requests come from known gateways.
 *
 * Checks the request IP against a configurable list of allowed IPs,
 * and optionally verifies a request signature header.
 */
class UssdAuthentication
{
    public function handle(Request $request, Closure $next): Response
    {
        $config = (array) config('ussd.gateway_authentication', []);

        if (! ($config['enabled'] ?? false)) {
            return $next($request);
        }

        $allowedIps = (array) ($config['allowed_ips'] ?? []);

        if ($allowedIps !== [] && ! in_array($request->ip(), $allowedIps, true)) {
            return response()->json(['error' => 'Unauthorized gateway IP.'], 403);
        }

        $signatureHeader = isset($config['signature_header']) && is_string($config['signature_header']) ? $config['signature_header'] : null;
        $signatureSecret = isset($config['signature_secret']) && is_string($config['signature_secret']) ? $config['signature_secret'] : null;

        if ($signatureHeader !== null && $signatureSecret !== null) {
            $providedSignature = $request->header($signatureHeader);

            if ($providedSignature === null) {
                return response()->json(['error' => 'Missing gateway signature.'], 403);
            }

            $payload = $request->getContent();
            $expectedSignature = hash_hmac('sha256', $payload, $signatureSecret);

            if (! is_string($providedSignature) || ! hash_equals($expectedSignature, $providedSignature)) {
                return response()->json(['error' => 'Invalid gateway signature.'], 403);
            }
        }

        return $next($request);
    }
}
