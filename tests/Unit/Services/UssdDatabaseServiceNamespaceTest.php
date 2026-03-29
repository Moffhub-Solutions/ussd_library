<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Tests\Unit\Services;

use Moffhub\Ussd\Models\UssdMenuStatistic;
use Moffhub\Ussd\Services\UssdDatabaseService;
use Moffhub\Ussd\Tests\TestCase;

class UssdDatabaseServiceNamespaceTest extends TestCase
{
    public function test_database_service_imports_package_model_namespace(): void
    {
        $reflection = new \ReflectionClass(UssdDatabaseService::class);
        $fileName = $reflection->getFileName();
        $this->assertIsString($fileName);
        $source = file_get_contents($fileName);
        $this->assertIsString($source);

        $this->assertStringContainsString(
            'use Moffhub\\Ussd\\Models\\UssdMenuStatistic;',
            $source
        );
        $this->assertStringNotContainsString(
            'use App\\Models\\UssdMenuStatistic;',
            $source
        );
    }

    public function test_ussd_menu_statistic_model_uses_package_namespace(): void
    {
        $reflection = new \ReflectionClass(UssdMenuStatistic::class);

        $this->assertEquals('Moffhub\\Ussd\\Models', $reflection->getNamespaceName());
    }

    public function test_config_has_model_namespace_key(): void
    {
        $config = require dirname(__DIR__, 3).'/src/Config/ussd.php';

        $this->assertArrayHasKey('database', $config);
        $this->assertArrayHasKey('model_namespace', $config['database']);
        $this->assertEquals('Moffhub\\Ussd\\Models', $config['database']['model_namespace']);
    }
}
