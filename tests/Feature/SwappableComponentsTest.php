<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Tests\Feature;

use Illuminate\Http\Request;
use Moffhub\Ussd\Builders\UssdBuilder;
use Moffhub\Ussd\Interfaces\RateLimiterInterface;
use Moffhub\Ussd\Menus\SimpleMenu;
use Moffhub\Ussd\Tests\TestCase;
use Moffhub\Ussd\UssdFramework;

class SwappableComponentsTest extends TestCase
{
    private function denyingLimiter(): RateLimiterInterface
    {
        return new class implements RateLimiterInterface
        {
            public function allow(string $phoneNumber, string $action = 'request'): bool
            {
                return false;
            }
        };
    }

    private function dial(UssdFramework $framework): string
    {
        return $framework->handle(Request::create('/', 'POST', [
            'phoneNumber' => '254700000000',
            'text' => '',
            'sessionId' => 'swap-test',
            'serviceCode' => '*123#',
        ]))->getMessage();
    }

    public function test_container_binding_overrides_the_rate_limiter(): void
    {
        app()->bind(RateLimiterInterface::class, fn (): RateLimiterInterface => $this->denyingLimiter());

        $framework = new UssdFramework([
            'security' => ['rate_limiting' => true, 'input_sanitization' => false, 'audit_logging' => false],
            'analytics' => ['enabled' => false],
            'database' => ['enabled' => false],
        ]);
        $framework->registerMenu('main', new SimpleMenu('Welcome', []));

        $this->assertStringContainsString('temporarily unavailable', $this->dial($framework));
    }

    public function test_builder_setter_overrides_the_rate_limiter(): void
    {
        $framework = UssdBuilder::create([
            'security' => ['rate_limiting' => true, 'input_sanitization' => false, 'audit_logging' => false],
            'analytics' => ['enabled' => false],
            'database' => ['enabled' => false],
        ])
            ->simpleMenu('main', 'Welcome')
            ->rateLimiter($this->denyingLimiter())
            ->build();

        $this->assertStringContainsString('temporarily unavailable', $this->dial($framework));
    }

    public function test_default_is_used_when_nothing_is_bound(): void
    {
        // No binding, no override: a normal dial goes through. (DB-backed access
        // lists off so this stays a pure unit test with no migrated table.)
        $framework = new UssdFramework([
            'security' => ['rate_limiting' => true, 'input_sanitization' => false, 'audit_logging' => false],
            'rate_limiting' => ['use_database_lists' => false],
            'analytics' => ['enabled' => false],
            'database' => ['enabled' => false],
        ]);
        $framework->registerMenu('main', new SimpleMenu('Welcome', []));

        $this->assertStringContainsString('Welcome', $this->dial($framework));
    }
}
