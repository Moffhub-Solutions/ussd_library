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
            $this->singleHeader($request, self::TIMESTAMP_HEADER),
            $this->singleHeader($request, self::SIGNATURE_HEADER),
            $now,
        );
    }

    /**
     * The one value of a header, or null unless there is exactly one.
     *
     * `Request::header()` hands back the first value when a header is sent more
     * than once, which would let a request carrying both a genuine signature
     * and a forged one verify on whichever arrived first. A request that names
     * its own signature twice is ambiguous rather than signed, so it is
     * refused instead of resolved.
     *
     * @return non-empty-string|null
     */
    protected function singleHeader(Request $request, string $name): ?string
    {
        $values = $request->headers->all($name);

        if (count($values) !== 1) {
            return null;
        }

        $value = reset($values);

        return is_string($value) && $value !== '' ? $value : null;
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
