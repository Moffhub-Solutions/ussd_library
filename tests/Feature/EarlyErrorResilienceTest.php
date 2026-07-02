<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Tests\Feature;

use Illuminate\Http\Request;
use Moffhub\Ussd\Menus\SimpleMenu;
use Moffhub\Ussd\Tests\TestCase;
use Moffhub\Ussd\UssdFramework;

class EarlyErrorResilienceTest extends TestCase
{
    /**
     * With DB-backed access lists enabled but the ussd_access_lists table not
     * migrated, a dial must still return a normal response (fail open) rather
     * than crashing the request path.
     */
    public function test_missing_access_list_table_fails_open(): void
    {
        $framework = new UssdFramework([
            'security' => ['rate_limiting' => true, 'input_sanitization' => false, 'audit_logging' => false],
            'rate_limiting' => ['use_database_lists' => true],
            'analytics' => ['enabled' => false],
            'database' => ['enabled' => false],
        ]);
        $framework->registerMenu('main', new SimpleMenu('Welcome', []));

        $response = $framework->handle(Request::create('/', 'POST', [
            'phoneNumber' => '254700000000',
            'text' => '',
            'sessionId' => 'resilience-test',
            'serviceCode' => '*123#',
        ]));

        $this->assertStringContainsString('Welcome', $response->getMessage());
    }
}
