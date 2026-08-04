<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Security;

use Illuminate\Http\Request;

/**
 * Verifies a session forwarded by a multi-tenant USSD gateway.
 *
 * A tenant's callback URL is public. Without verification, anyone who guesses
 * it can spoof sessions, bypass the gateway's metering, and impersonate
 * subscribers. Consumers should call this on every inbound leg and reject
 * anything that does not pass.
 *
 * The signature covers "{timestamp}.{raw body}" with HMAC-SHA256 under the
 * slice's signing secret, so a replay outside the tolerance window fails even
 * when the signature itself is valid.
 */
class GatewaySignatureVerifier
{
    public const TIMESTAMP_HEADER = 'X-Ussd-Timestamp';

    public const SIGNATURE_HEADER = 'X-Ussd-Signature';

    public function __construct(
        protected string $secret,
        protected int $toleranceSeconds = 60,
    ) {}

    public function verifyRequest(Request $request, ?int $now = null): bool
    {
        return $this->verify(
            $request->getContent(),
            $request->header(self::TIMESTAMP_HEADER),
            $request->header(self::SIGNATURE_HEADER),
            $now,
        );
    }

    public function verify(string $body, ?string $timestamp, ?string $signature, ?int $now = null): bool
    {
        if ($this->secret === '' || $timestamp === null || $signature === null) {
            return false;
        }

        if (! ctype_digit($timestamp)) {
            return false;
        }

        $now ??= time();

        if (abs($now - (int) $timestamp) > $this->toleranceSeconds) {
            return false;
        }

        return hash_equals($this->sign($body, (int) $timestamp), $signature);
    }

    public function sign(string $body, int $timestamp): string
    {
        return hash_hmac('sha256', $timestamp.'.'.$body, $this->secret);
    }
}
