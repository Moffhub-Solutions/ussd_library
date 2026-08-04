<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Tests\Unit\Security;

use Illuminate\Http\Request;
use Moffhub\Ussd\Security\GatewaySignatureVerifier;
use Moffhub\Ussd\Tests\TestCase;

class GatewaySignatureVerifierTest extends TestCase
{
    private const SECRET = 'slice-signing-secret';

    private const BODY = '{"sessionId":"abc","phoneNumber":"+254712345678","serviceCode":"*483*03*01#","text":"1"}';

    public function test_a_correctly_signed_request_verifies(): void
    {
        $verifier = new GatewaySignatureVerifier(self::SECRET);
        $timestamp = 1_800_000_000;

        $this->assertTrue($verifier->verify(
            self::BODY,
            (string) $timestamp,
            $verifier->sign(self::BODY, $timestamp),
            $timestamp,
        ));
    }

    public function test_a_tampered_body_fails(): void
    {
        $verifier = new GatewaySignatureVerifier(self::SECRET);
        $timestamp = 1_800_000_000;
        $signature = $verifier->sign(self::BODY, $timestamp);

        $this->assertFalse($verifier->verify(
            str_replace('"1"', '"2"', self::BODY),
            (string) $timestamp,
            $signature,
            $timestamp,
        ));
    }

    public function test_a_wrong_secret_fails(): void
    {
        $signed = (new GatewaySignatureVerifier('other-secret'))->sign(self::BODY, 1_800_000_000);

        $this->assertFalse(
            (new GatewaySignatureVerifier(self::SECRET))
                ->verify(self::BODY, '1800000000', $signed, 1_800_000_000),
        );
    }

    public function test_a_stale_signature_fails_even_though_it_is_valid(): void
    {
        $verifier = new GatewaySignatureVerifier(self::SECRET, 60);
        $timestamp = 1_800_000_000;
        $signature = $verifier->sign(self::BODY, $timestamp);

        $this->assertFalse($verifier->verify(
            self::BODY,
            (string) $timestamp,
            $signature,
            $timestamp + 61,
        ));
    }

    public function test_missing_headers_fail(): void
    {
        $verifier = new GatewaySignatureVerifier(self::SECRET);

        $this->assertFalse($verifier->verify(self::BODY, null, null));
        $this->assertFalse($verifier->verify(self::BODY, 'not-a-timestamp', 'deadbeef'));
    }

    public function test_it_reads_the_headers_off_a_request(): void
    {
        $verifier = new GatewaySignatureVerifier(self::SECRET);
        $timestamp = 1_800_000_000;

        $request = Request::create('/ussd', 'POST', [], [], [], [
            'HTTP_'.str_replace('-', '_', strtoupper(GatewaySignatureVerifier::TIMESTAMP_HEADER)) => (string) $timestamp,
            'HTTP_'.str_replace('-', '_', strtoupper(GatewaySignatureVerifier::SIGNATURE_HEADER)) => $verifier->sign(self::BODY, $timestamp),
        ], self::BODY);

        $this->assertTrue($verifier->verifyRequest($request, $timestamp));
    }
}
