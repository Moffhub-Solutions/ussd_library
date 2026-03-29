<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Tests\Unit\Facades;

use Moffhub\Ussd\Facades\Ussd;
use Moffhub\Ussd\Tests\TestCase;
use Moffhub\Ussd\UssdFramework;

class UssdFacadeTest extends TestCase
{
    public function test_facade_resolves_to_framework_instance(): void
    {
        $instance = Ussd::getFacadeRoot();

        $this->assertInstanceOf(UssdFramework::class, $instance);
    }

    public function test_facade_returns_singleton(): void
    {
        $instance1 = Ussd::getFacadeRoot();
        $instance2 = Ussd::getFacadeRoot();

        $this->assertSame($instance1, $instance2);
    }

    public function test_facade_can_call_get_config(): void
    {
        $config = Ussd::getConfig();

        $this->assertIsArray($config);
    }

    public function test_facade_can_call_get_health_status(): void
    {
        $health = Ussd::getHealthStatus();

        $this->assertIsArray($health);
        $this->assertArrayHasKey('status', $health);
    }

    public function test_facade_can_check_menu_existence(): void
    {
        $this->assertFalse(Ussd::hasMenu('nonexistent'));
    }
}
