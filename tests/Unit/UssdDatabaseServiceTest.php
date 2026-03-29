<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Tests\Unit;

use Moffhub\Ussd\Services\UssdDatabaseService;
use Moffhub\Ussd\Tests\TestCase;

class UssdDatabaseServiceTest extends TestCase
{
    private UssdDatabaseService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new UssdDatabaseService([
            'enable_database_logging' => false, // Disable actual DB calls for unit tests
            'anonymize_phone_numbers' => true,
            'retention_days' => [
                'rate_limits' => 30,
                'security_events' => 90,
                'analytics' => 365,
                'user_sessions' => 365,
                'recovery_logs' => 90,
                'performance_metrics' => 30,
                'business_metrics' => 730,
            ],
        ]);
    }

    // ==================== CRUD - disabled returns false/0 ====================

    public function test_save_rate_limit_returns_false_when_disabled(): void
    {
        $result = $this->service->saveRateLimit('+254712345678', 'test_action', [time()]);

        $this->assertFalse($result);
    }

    public function test_save_security_event_returns_false_when_disabled(): void
    {
        $result = $this->service->saveSecurityEvent('test_event', '+254712345678', [], 'low');

        $this->assertFalse($result);
    }

    public function test_save_user_session_returns_false_when_disabled(): void
    {
        $result = $this->service->saveUserSession('sess_123', '+254712345678', 'main', []);

        $this->assertFalse($result);
    }

    public function test_save_session_analytics_returns_false_when_disabled(): void
    {
        $result = $this->service->saveSessionAnalytics('test_event');

        $this->assertFalse($result);
    }

    public function test_save_recovery_log_returns_zero_when_disabled(): void
    {
        $result = $this->service->saveRecoveryLog('+254712345678', 'sess_123', 'form_recovery', [], 'timeout');

        $this->assertEquals(0, $result);
    }

    public function test_complete_recovery_log_returns_false_when_disabled(): void
    {
        $result = $this->service->completeRecoveryLog(1, true, ['data' => 'recovered']);

        $this->assertFalse($result);
    }

    public function test_complete_recovery_log_returns_false_for_zero_id(): void
    {
        $enabledService = new UssdDatabaseService(['enable_database_logging' => true]);
        $result = $enabledService->completeRecoveryLog(0, true);

        $this->assertFalse($result);
    }

    public function test_save_performance_metrics_returns_false_when_disabled(): void
    {
        $result = $this->service->savePerformanceMetrics('menu_render', 100);

        $this->assertFalse($result);
    }

    public function test_save_business_metrics_returns_false_when_disabled(): void
    {
        $result = $this->service->saveBusinessMetrics('revenue', 500, 'financial');

        $this->assertFalse($result);
    }

    public function test_update_menu_statistics_returns_false_when_disabled(): void
    {
        $result = $this->service->updateMenuStatistics('main_menu', '1');

        $this->assertFalse($result);
    }

    // ==================== Phone number hashing ====================

    public function test_phone_number_is_hashed_when_anonymize_enabled(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('hashPhoneNumber');
        $method->setAccessible(true);

        $hashed = $method->invoke($this->service, '+254712345678');

        $this->assertNotEquals('+254712345678', $hashed);
        $this->assertEquals(hash('sha256', '+254712345678'), $hashed);
    }

    public function test_phone_number_not_hashed_when_anonymize_disabled(): void
    {
        $service = new UssdDatabaseService([
            'anonymize_phone_numbers' => false,
        ]);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('hashPhoneNumber');
        $method->setAccessible(true);

        $hashed = $method->invoke($service, '+254712345678');

        $this->assertEquals('+254712345678', $hashed);
    }

    // ==================== Table name mapping ====================

    public function test_get_table_name_mappings(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getTableName');
        $method->setAccessible(true);

        $this->assertEquals('ussd_rate_limits', $method->invoke($this->service, 'rate_limits'));
        $this->assertEquals('ussd_security_events', $method->invoke($this->service, 'security_events'));
        $this->assertEquals('ussd_analytics', $method->invoke($this->service, 'analytics'));
        $this->assertEquals('ussd_user_sessions', $method->invoke($this->service, 'user_sessions'));
        $this->assertEquals('ussd_session_recovery_logs', $method->invoke($this->service, 'recovery_logs'));
        $this->assertEquals('ussd_performance_metrics', $method->invoke($this->service, 'performance_metrics'));
        $this->assertEquals('ussd_business_metrics', $method->invoke($this->service, 'business_metrics'));
    }

    public function test_get_table_name_default(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getTableName');
        $method->setAccessible(true);

        $this->assertEquals('ussd_custom_table', $method->invoke($this->service, 'custom_table'));
    }

    // ==================== Timestamp column mapping ====================

    public function test_get_timestamp_column_mappings(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getTimestampColumn');
        $method->setAccessible(true);

        $this->assertEquals('updated_at', $method->invoke($this->service, 'rate_limits'));
        $this->assertEquals('detected_at', $method->invoke($this->service, 'security_events'));
        $this->assertEquals('timestamp', $method->invoke($this->service, 'analytics'));
        $this->assertEquals('started_at', $method->invoke($this->service, 'user_sessions'));
        $this->assertEquals('attempted_at', $method->invoke($this->service, 'recovery_logs'));
        $this->assertEquals('timestamp', $method->invoke($this->service, 'performance_metrics'));
        $this->assertEquals('recorded_at', $method->invoke($this->service, 'business_metrics'));
    }

    public function test_get_timestamp_column_default(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getTimestampColumn');
        $method->setAccessible(true);

        $this->assertEquals('created_at', $method->invoke($this->service, 'unknown_type'));
    }

    // ==================== User journey extraction ====================

    public function test_extract_user_journey_from_session_data(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('extractUserJourneyFromSessionData');
        $method->setAccessible(true);

        $sessionData = [
            'menu_history' => [
                ['menu' => 'main', 'timestamp' => '2024-01-01T00:00:00', 'step' => 0],
                ['menu' => 'settings', 'timestamp' => '2024-01-01T00:01:00', 'step' => 1],
            ],
            'current_menu' => 'payment',
            'step' => 2,
        ];

        $journey = $method->invoke($this->service, $sessionData);

        $this->assertCount(3, $journey);
        $this->assertEquals('main', $journey[0]['menu']);
        $this->assertEquals('settings', $journey[1]['menu']);
        $this->assertEquals('payment', $journey[2]['menu']);
        $this->assertTrue($journey[2]['current']);
    }

    public function test_extract_user_journey_with_no_history(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('extractUserJourneyFromSessionData');
        $method->setAccessible(true);

        $sessionData = [
            'current_menu' => 'main',
            'step' => 0,
        ];

        $journey = $method->invoke($this->service, $sessionData);

        $this->assertCount(1, $journey);
        $this->assertEquals('main', $journey[0]['menu']);
    }

    public function test_extract_user_journey_empty_data(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('extractUserJourneyFromSessionData');
        $method->setAccessible(true);

        $journey = $method->invoke($this->service, []);

        $this->assertEmpty($journey);
    }

    // ==================== Configurable namespace ====================

    public function test_default_config_values(): void
    {
        $service = new UssdDatabaseService;

        $reflection = new \ReflectionClass($service);
        $property = $reflection->getProperty('config');
        $property->setAccessible(true);

        $config = $property->getValue($service);

        $this->assertTrue($config['enable_database_logging']);
        $this->assertTrue($config['anonymize_phone_numbers']);
        $this->assertArrayHasKey('retention_days', $config);
    }

    public function test_config_override(): void
    {
        $service = new UssdDatabaseService([
            'enable_database_logging' => false,
            'anonymize_phone_numbers' => false,
        ]);

        $reflection = new \ReflectionClass($service);
        $property = $reflection->getProperty('config');
        $property->setAccessible(true);

        $config = $property->getValue($service);

        $this->assertFalse($config['enable_database_logging']);
        $this->assertFalse($config['anonymize_phone_numbers']);
    }
}
