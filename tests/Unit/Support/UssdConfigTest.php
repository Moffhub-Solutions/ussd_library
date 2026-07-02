<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Tests\Unit\Support;

use Moffhub\Ussd\Support\UssdConfig;
use Moffhub\Ussd\Tests\TestCase;
use Moffhub\Ussd\UssdFramework;

class UssdConfigTest extends TestCase
{
    public function test_dot_notation_get_and_defaults(): void
    {
        $config = UssdConfig::fromArray([
            'debug' => true,
            'deduplication' => ['enabled' => false, 'window' => 12],
        ]);

        $this->assertTrue($config->get('debug'));
        $this->assertFalse($config->get('deduplication.enabled'));
        $this->assertSame(12, $config->get('deduplication.window'));
        $this->assertSame('fallback', $config->get('missing.key', 'fallback'));
        $this->assertTrue($config->has('deduplication.window'));
        $this->assertFalse($config->has('deduplication.nope'));
    }

    public function test_typed_getters(): void
    {
        $config = UssdConfig::fromArray([
            'session_timeout' => '300',
            'default_menu' => 'home',
        ]);

        $this->assertSame(300, $config->int('session_timeout'));
        $this->assertSame('home', $config->defaultMenu());
        $this->assertSame(5, $config->deduplicationWindow()); // default when absent
        $this->assertTrue($config->validateMenuReferences());  // default true
    }

    public function test_merge_is_recursive_and_preserves_siblings(): void
    {
        $config = UssdConfig::fromArray([
            'security' => ['rate_limiting' => true, 'input_sanitization' => true],
        ]);

        $merged = $config->merge(['security' => ['input_sanitization' => false]]);

        // The overridden key changes...
        $this->assertFalse($merged->get('security.input_sanitization'));
        // ...but the sibling is preserved (the bug plain array_merge caused).
        $this->assertTrue($merged->get('security.rate_limiting'));
        // Original is untouched (immutable).
        $this->assertTrue($config->get('security.input_sanitization'));
    }

    public function test_framework_exposes_typed_config(): void
    {
        $framework = new UssdFramework([
            'debug' => true,
            'analytics' => ['enabled' => false],
            'database' => ['enabled' => false],
        ]);

        $this->assertInstanceOf(UssdConfig::class, $framework->config());
        $this->assertTrue($framework->config()->debug());
        $this->assertIsArray($framework->getConfig()); // raw array still available
    }
}
