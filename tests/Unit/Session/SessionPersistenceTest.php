<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Tests\Unit\Session;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Moffhub\Ussd\Tests\TestCase;
use Moffhub\Ussd\UssdFramework;

class SessionPersistenceTest extends TestCase
{
    private UssdFramework $framework;

    protected function setUp(): void
    {
        parent::setUp();
        $this->framework = new UssdFramework([
            'session_prefix' => 'ussd_session_',
            'session_timeout' => 300,
            'grace_period' => 600,
            'max_inactive_time' => 1800,
            'persistence_strategy' => 'hybrid',
            'enable_intelligent_recovery' => true,
            'enable_context_preservation' => true,
            'cache' => ['enabled' => true],
            'database' => ['enabled' => false],
            'security' => [
                'rate_limiting' => false,
                'input_sanitization' => false,
                'audit_logging' => false,
            ],
            'analytics' => ['enabled' => false],
        ]);
    }

    // ==================== 1.1 Session Retrieval from Cache ====================

    public function test_retrieve_session_returns_data_from_cache(): void
    {
        $phoneNumber = '+254712345678';
        $cacheKey = 'ussd_session_'.$phoneNumber;
        $sessionData = [
            'current_menu' => 'main',
            'menu_history' => [],
            'form_data' => ['name' => 'John'],
            'step' => 2,
            'updated_at' => Carbon::now()->toISOString(),
        ];

        Cache::put($cacheKey, $sessionData, 300);

        $result = $this->invokeRetrieveSession($phoneNumber);

        $this->assertNotNull($result);
        $this->assertEquals('main', $result['current_menu']);
        $this->assertEquals(['name' => 'John'], $result['form_data']);
        $this->assertEquals(2, $result['step']);
    }

    public function test_retrieve_session_returns_null_when_no_session_exists(): void
    {
        $result = $this->invokeRetrieveSession('+254700000000');

        $this->assertNull($result);
    }

    public function test_retrieve_session_returns_null_for_expired_beyond_max_inactive(): void
    {
        $phoneNumber = '+254712345678';
        $cacheKey = 'ussd_session_'.$phoneNumber;
        $sessionData = [
            'current_menu' => 'main',
            'updated_at' => Carbon::now()->subSeconds(2000)->toISOString(),
        ];

        Cache::put($cacheKey, $sessionData, 3000);

        $result = $this->invokeRetrieveSession($phoneNumber);

        $this->assertNull($result);
    }

    public function test_retrieve_session_returns_data_within_grace_period(): void
    {
        $phoneNumber = '+254712345678';
        $cacheKey = 'ussd_session_'.$phoneNumber;
        $sessionData = [
            'current_menu' => 'settings',
            'updated_at' => Carbon::now()->subSeconds(400)->toISOString(),
        ];

        Cache::put($cacheKey, $sessionData, 3000);

        $result = $this->invokeRetrieveSession($phoneNumber);

        $this->assertNotNull($result);
        $this->assertEquals('settings', $result['current_menu']);
    }

    public function test_retrieve_session_restores_navigation_history(): void
    {
        $phoneNumber = '+254712345678';
        $cacheKey = 'ussd_session_'.$phoneNumber;
        $sessionData = [
            'current_menu' => 'payment',
            'menu_history' => [
                ['menu' => 'main', 'data' => [], 'step' => 0],
                ['menu' => 'services', 'data' => [], 'step' => 0],
            ],
            'form_data' => ['amount' => '100'],
            'step' => 1,
            'updated_at' => Carbon::now()->toISOString(),
            'user_data' => ['account' => '12345'],
        ];

        Cache::put($cacheKey, $sessionData, 300);

        $result = $this->invokeRetrieveSession($phoneNumber);

        $this->assertNotNull($result);
        $this->assertEquals('payment', $result['current_menu']);
        $this->assertCount(2, $result['menu_history']);
        $this->assertEquals('100', $result['form_data']['amount']);
        $this->assertEquals('12345', $result['user_data']['account']);
    }

    // ==================== 1.2 Session Migration ====================

    public function test_migrate_session_returns_true_on_success(): void
    {
        $fromPhone = '+254712345678';
        $toPhone = '+254787654321';
        $cacheKey = 'ussd_session_'.$fromPhone;

        Cache::put($cacheKey, [
            'current_menu' => 'main',
            'step' => 0,
        ], 300);

        $result = $this->framework->migrateSession($fromPhone, $toPhone);

        $this->assertTrue($result);
    }

    public function test_migrate_session_moves_data_between_keys(): void
    {
        $fromPhone = '+254712345678';
        $toPhone = '+254787654321';
        $fromKey = 'ussd_session_'.$fromPhone;
        $toKey = 'ussd_session_'.$toPhone;

        $sessionData = [
            'current_menu' => 'settings',
            'form_data' => ['name' => 'Test'],
        ];

        Cache::put($fromKey, $sessionData, 300);

        $this->framework->migrateSession($fromPhone, $toPhone);

        $this->assertNull(Cache::get($fromKey));
        $migrated = Cache::get($toKey);
        $this->assertNotNull($migrated);
        $this->assertEquals('settings', $migrated['current_menu']);
    }

    public function test_migrate_session_returns_false_when_disabled(): void
    {
        $framework = new UssdFramework([
            'enable_session_migration' => false,
            'cache' => ['enabled' => false],
            'database' => ['enabled' => false],
            'security' => [
                'rate_limiting' => false,
                'input_sanitization' => false,
                'audit_logging' => false,
            ],
            'analytics' => ['enabled' => false],
        ]);

        $result = $framework->migrateSession('+254712345678', '+254787654321');

        $this->assertFalse($result);
    }

    public function test_migrate_session_returns_false_when_no_source_session(): void
    {
        $result = $this->framework->migrateSession('+254700000000', '+254711111111');

        $this->assertFalse($result);
    }

    // ==================== 1.3 Recovery Handler Signature ====================

    public function test_recovery_handler_receives_only_session_data(): void
    {
        $receivedArgs = null;

        $framework = new UssdFramework([
            'enable_intelligent_recovery' => true,
            'cache' => ['enabled' => false],
            'database' => ['enabled' => false],
            'security' => [
                'rate_limiting' => false,
                'input_sanitization' => false,
                'audit_logging' => false,
            ],
            'analytics' => ['enabled' => false],
        ]);

        // Access protected sessionRecoveryHandlers via reflection
        $reflection = new \ReflectionClass($framework);
        $prop = $reflection->getProperty('sessionRecoveryHandlers');
        $prop->setAccessible(true);

        $handlers = $prop->getValue($framework);
        $handlers['test_handler'] = function (array $sessionData) use (&$receivedArgs): ?array {
            $receivedArgs = func_get_args();

            return ['type' => 'test', 'suggested_action' => 'continue'];
        };
        $prop->setValue($framework, $handlers);

        // Invoke attemptIntelligentRecovery via reflection
        $method = $reflection->getMethod('attemptIntelligentRecovery');
        $method->setAccessible(true);

        $request = new \Illuminate\Http\Request;
        $sessionData = ['current_menu' => 'main', 'form_data' => ['name' => 'Test']];

        $result = $method->invoke($framework, $sessionData, $request);

        $this->assertNotNull($receivedArgs);
        $this->assertCount(1, $receivedArgs);
        $this->assertEquals($sessionData, $receivedArgs[0]);
        $this->assertEquals('test', $result['type']);
    }

    public function test_default_form_recovery_handler_works_with_single_arg(): void
    {
        $framework = new UssdFramework([
            'enable_intelligent_recovery' => true,
            'cache' => ['enabled' => false],
            'database' => ['enabled' => false],
            'security' => [
                'rate_limiting' => false,
                'input_sanitization' => false,
                'audit_logging' => false,
            ],
            'analytics' => ['enabled' => false],
        ]);

        $reflection = new \ReflectionClass($framework);
        $method = $reflection->getMethod('attemptIntelligentRecovery');
        $method->setAccessible(true);

        $request = new \Illuminate\Http\Request;
        $sessionData = [
            'form_data' => ['field1' => 'val1', 'field2' => 'val2', 'field3' => 'val3'],
            'form_config' => ['fields' => ['field1', 'field2', 'field3', 'field4']],
        ];

        $result = $method->invoke($framework, $sessionData, $request);

        $this->assertNotNull($result);
        $this->assertEquals('form_recovery', $result['type']);
        $this->assertEquals(75, $result['completion_percentage']);
    }

    // ==================== Helper ====================

    private function invokeRetrieveSession(string $phoneNumber): ?array
    {
        $reflection = new \ReflectionClass($this->framework);
        $method = $reflection->getMethod('retrieveSession');
        $method->setAccessible(true);

        return $method->invoke($this->framework, $phoneNumber);
    }
}
