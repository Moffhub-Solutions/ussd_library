<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Security;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RequestVerifier
{
    protected bool $enabled;

    /** @var array<string, mixed> */
    protected array $providerSecrets;

    public function __construct()
    {
        $this->enabled = (bool) config('ussd.verify_signatures', false);
        $this->providerSecrets = (array) config('ussd.providers', []);
    }

    /**
     * Verify a request signature for a given provider.
     */
    public function verify(Request $request, string $providerName): bool
    {
        if (! $this->enabled) {
            return true;
        }

        $secret = $this->getProviderSecret($providerName);

        if ($secret === null) {
            Log::warning('RequestVerifier: No secret configured for provider', [
                'provider' => $providerName,
            ]);

            return false;
        }

        return match ($providerName) {
            'safaricom', 'africas_talking', 'at' => $this->verifySafaricom($request, $secret),
            'airtel' => $this->verifyAirtel($request, $secret),
            'mtn' => $this->verifyMtn($request, $secret),
            default => $this->verifyGeneric($request, $secret),
        };
    }

    /**
     * Verify Safaricom/Africa's Talking API key signature.
     *
     * Safaricom uses an API key passed in the request header.
     */
    protected function verifySafaricom(Request $request, string $secret): bool
    {
        $apiKey = $request->header('X-API-Key')
            ?? $request->header('apiKey')
            ?? $request->input('apiKey');

        if ($apiKey === null) {
            Log::warning('RequestVerifier: Missing API key in Safaricom request');

            return false;
        }

        return hash_equals($secret, $apiKey);
    }

    /**
     * Verify Airtel callback token.
     *
     * Airtel sends a token in the Authorization header or as a request parameter.
     */
    protected function verifyAirtel(Request $request, string $secret): bool
    {
        $token = $request->header('Authorization')
            ?? $request->header('X-Callback-Token')
            ?? $request->input('token');

        if ($token === null) {
            Log::warning('RequestVerifier: Missing callback token in Airtel request');

            return false;
        }

        // Strip "Bearer " prefix if present
        if (str_starts_with($token, 'Bearer ')) {
            $token = substr($token, 7);
        }

        return hash_equals($secret, $token);
    }

    /**
     * Verify MTN request using HMAC signature.
     *
     * MTN sends an HMAC-SHA256 signature of the request body.
     */
    protected function verifyMtn(Request $request, string $secret): bool
    {
        $signature = $request->header('X-Signature')
            ?? $request->header('X-MTN-Signature');

        if ($signature === null) {
            Log::warning('RequestVerifier: Missing signature in MTN request');

            return false;
        }

        $payload = $request->getContent();
        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Generic HMAC verification for custom providers.
     */
    protected function verifyGeneric(Request $request, string $secret): bool
    {
        $signature = $request->header('X-Signature')
            ?? $request->header('X-HMAC-Signature');

        if ($signature === null) {
            Log::warning('RequestVerifier: Missing signature in generic request');

            return false;
        }

        $payload = $request->getContent();
        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Get the secret key for a provider from config.
     */
    protected function getProviderSecret(string $providerName): ?string
    {
        $secret = $this->providerSecrets[$providerName]['secret'] ?? null;

        if (is_string($secret) && $secret !== '') {
            return $secret;
        }

        return null;
    }

    /**
     * Check if signature verification is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}
