<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Tests\Unit\Providers;

use Illuminate\Http\Request;
use InvalidArgumentException;
use Moffhub\Ussd\Interfaces\UssdProviderInterface;
use Moffhub\Ussd\Providers\AirtelProvider;
use Moffhub\Ussd\Providers\GenericProvider;
use Moffhub\Ussd\Providers\MtnProvider;
use Moffhub\Ussd\Providers\ProviderFactory;
use Moffhub\Ussd\Providers\SafaricomProvider;
use Moffhub\Ussd\Tests\TestCase;

class ProviderFactoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ProviderFactory::clearCache();
    }

    public function test_create_safaricom_provider(): void
    {
        $provider = ProviderFactory::create('safaricom');

        $this->assertInstanceOf(SafaricomProvider::class, $provider);
        $this->assertEquals('safaricom', $provider->getName());
    }

    public function test_create_africas_talking_alias(): void
    {
        $provider = ProviderFactory::create('africas_talking');

        $this->assertInstanceOf(SafaricomProvider::class, $provider);
    }

    public function test_create_at_alias(): void
    {
        $provider = ProviderFactory::create('at');

        $this->assertInstanceOf(SafaricomProvider::class, $provider);
    }

    public function test_create_airtel_provider(): void
    {
        $provider = ProviderFactory::create('airtel');

        $this->assertInstanceOf(AirtelProvider::class, $provider);
        $this->assertEquals('airtel', $provider->getName());
    }

    public function test_create_mtn_provider(): void
    {
        $provider = ProviderFactory::create('mtn');

        $this->assertInstanceOf(MtnProvider::class, $provider);
        $this->assertEquals('mtn', $provider->getName());
    }

    public function test_create_generic_provider(): void
    {
        $provider = ProviderFactory::create('generic');

        $this->assertInstanceOf(GenericProvider::class, $provider);
        $this->assertEquals('generic', $provider->getName());
    }

    public function test_create_unknown_provider_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown USSD provider: unknown');

        ProviderFactory::create('unknown');
    }

    public function test_create_is_case_insensitive(): void
    {
        $provider1 = ProviderFactory::create('SAFARICOM');
        $provider2 = ProviderFactory::create('Safaricom');
        $provider3 = ProviderFactory::create('safaricom');

        $this->assertInstanceOf(SafaricomProvider::class, $provider1);
        $this->assertInstanceOf(SafaricomProvider::class, $provider2);
        $this->assertInstanceOf(SafaricomProvider::class, $provider3);
    }

    public function test_detect_safaricom_from_request_fields(): void
    {
        $request = Request::create('/', 'POST', [
            'phoneNumber' => '+254712345678',
            'sessionId' => 'session123',
            'text' => '1',
        ]);

        $provider = ProviderFactory::detect($request);

        $this->assertInstanceOf(SafaricomProvider::class, $provider);
    }

    public function test_detect_mtn_from_user_answer_field(): void
    {
        $request = Request::create('/', 'POST', [
            'msisdn' => '+234812345678',
            'UserAnswer' => '1',
        ]);

        $provider = ProviderFactory::detect($request);

        $this->assertInstanceOf(MtnProvider::class, $provider);
    }

    public function test_detect_airtel_from_transaction_id_field(): void
    {
        $request = Request::create('/', 'POST', [
            'msisdn' => '+254712345678',
            'transactionId' => 'txn123',
            'input' => '1',
        ]);

        $provider = ProviderFactory::detect($request);

        $this->assertInstanceOf(AirtelProvider::class, $provider);
    }

    public function test_detect_falls_back_to_generic(): void
    {
        $request = Request::create('/', 'POST', [
            'phone' => '+254712345678',
            'message' => '1',
        ]);

        $provider = ProviderFactory::detect($request);

        $this->assertInstanceOf(GenericProvider::class, $provider);
    }

    public function test_detect_uses_explicit_provider_from_config(): void
    {
        $request = Request::create('/', 'POST', [
            'phoneNumber' => '+254712345678',
        ]);

        $provider = ProviderFactory::detect($request, ['provider' => 'mtn']);

        $this->assertInstanceOf(MtnProvider::class, $provider);
    }

    public function test_detect_from_user_agent_safaricom(): void
    {
        $request = Request::create('/', 'POST', [
            'phone' => '+254712345678',
        ]);
        $request->headers->set('User-Agent', 'Africa Talking Gateway');

        $provider = ProviderFactory::detect($request);

        $this->assertInstanceOf(SafaricomProvider::class, $provider);
    }

    public function test_detect_from_user_agent_airtel(): void
    {
        $request = Request::create('/', 'POST', [
            'phone' => '+254712345678',
        ]);
        $request->headers->set('User-Agent', 'Airtel Money Gateway');

        $provider = ProviderFactory::detect($request);

        $this->assertInstanceOf(AirtelProvider::class, $provider);
    }

    public function test_detect_from_user_agent_mtn(): void
    {
        $request = Request::create('/', 'POST', [
            'phone' => '+234812345678',
        ]);
        $request->headers->set('User-Agent', 'MTN USSD Gateway');

        $provider = ProviderFactory::detect($request);

        $this->assertInstanceOf(MtnProvider::class, $provider);
    }

    public function test_register_custom_provider(): void
    {
        $customProvider = new class extends GenericProvider
        {
            protected string $name = 'custom';
        };

        ProviderFactory::register('custom', $customProvider::class);

        $this->assertTrue(ProviderFactory::has('custom'));

        $provider = ProviderFactory::create('custom');
        $this->assertEquals('custom', $provider->getName());
    }

    public function test_available_returns_all_provider_names(): void
    {
        $available = ProviderFactory::available();

        $this->assertContains('safaricom', $available);
        $this->assertContains('airtel', $available);
        $this->assertContains('mtn', $available);
        $this->assertContains('generic', $available);
    }

    public function test_has_checks_provider_existence(): void
    {
        $this->assertTrue(ProviderFactory::has('safaricom'));
        $this->assertTrue(ProviderFactory::has('SAFARICOM'));
        $this->assertFalse(ProviderFactory::has('nonexistent'));
    }

    public function test_create_applies_country_code_config(): void
    {
        $provider = ProviderFactory::create('airtel', ['country_code' => '256']);

        // The provider should have the country code set
        $request = Request::create('/', 'POST', [
            'msisdn' => '0712345678',
        ]);

        $phoneNumber = $provider->getPhoneNumber($request);
        $this->assertStringStartsWith('+256', $phoneNumber);
    }

    public function test_create_caches_instances(): void
    {
        $provider1 = ProviderFactory::create('safaricom');
        $provider2 = ProviderFactory::create('safaricom');

        $this->assertSame($provider1, $provider2);
    }

    public function test_create_with_different_config_creates_new_instance(): void
    {
        $provider1 = ProviderFactory::create('generic', ['country_code' => '254']);
        $provider2 = ProviderFactory::create('generic', ['country_code' => '256']);

        $this->assertNotSame($provider1, $provider2);
    }

    public function test_clear_cache_removes_cached_instances(): void
    {
        $provider1 = ProviderFactory::create('safaricom');
        ProviderFactory::clearCache();
        $provider2 = ProviderFactory::create('safaricom');

        $this->assertNotSame($provider1, $provider2);
    }
}
