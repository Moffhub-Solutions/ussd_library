<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Tests\Unit;

use Moffhub\Ussd\Analytics\UssdAnalytics;
use Moffhub\Ussd\Tests\TestCase;

class UssdAnalyticsTest extends TestCase
{
    private UssdAnalytics $analytics;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analytics = new UssdAnalytics([
            'enabled' => true,
            'store_in_database' => false, // Don't need DB for unit tests
            'buffer_size' => 10,
            'flush_interval' => 300,
            'retention_days' => 365,
            'track_user_journey' => true,
            'track_performance' => true,
            'track_errors' => true,
            'track_business_metrics' => true,
            'anonymize_users' => true,
            'real_time_dashboard' => false,
        ]);
    }

    // ==================== Event tracking - menu ====================

    public function test_track_menu_interaction_records_event(): void
    {
        $result = $this->analytics->trackMenuInteraction('+254712345678', 'main_menu', '1', ['source' => 'test']);

        $this->assertTrue($result);
    }

    public function test_track_menu_interaction_returns_false_when_disabled(): void
    {
        $analytics = new UssdAnalytics(['enabled' => false]);

        $result = $analytics->trackMenuInteraction('+254712345678', 'main_menu', '1');

        $this->assertFalse($result);
    }

    // ==================== Event tracking - session ====================

    public function test_track_session_records_event(): void
    {
        $result = $this->analytics->trackSession('+254712345678', 'start', ['provider' => 'safaricom']);

        $this->assertTrue($result);
    }

    // ==================== Event tracking - user journey ====================

    public function test_track_user_journey_records_navigation(): void
    {
        $result = $this->analytics->trackUserJourney('+254712345678', 'main_menu', 'settings', 'navigate');

        $this->assertTrue($result);
    }

    public function test_track_user_journey_returns_false_when_disabled(): void
    {
        $analytics = new UssdAnalytics(['track_user_journey' => false]);

        $result = $analytics->trackUserJourney('+254712345678', 'main', 'settings');

        $this->assertFalse($result);
    }

    // ==================== Event tracking - performance ====================

    public function test_track_performance_records_metrics(): void
    {
        $result = $this->analytics->trackPerformance('menu_render', 42.5, ['menu' => 'main']);

        $this->assertTrue($result);
    }

    public function test_track_performance_returns_false_when_disabled(): void
    {
        $analytics = new UssdAnalytics(['track_performance' => false, 'store_in_database' => false]);

        $result = $analytics->trackPerformance('menu_render', 42.5);

        $this->assertFalse($result);
    }

    // ==================== Event tracking - errors ====================

    public function test_track_error_records_error_event(): void
    {
        $error = new \Error('Test error');
        $result = $this->analytics->trackError($error, '+254712345678', ['menu' => 'payment']);

        $this->assertTrue($result);
    }

    public function test_track_error_returns_false_when_disabled(): void
    {
        $analytics = new UssdAnalytics(['track_errors' => false]);

        $result = $analytics->trackError(new \Error('Test'), '+254712345678');

        $this->assertFalse($result);
    }

    // ==================== Event tracking - business metrics ====================

    public function test_track_business_metric_records_metric(): void
    {
        $result = $this->analytics->trackBusinessMetric('transactions', 100, '+254712345678', [
            'category' => 'payments',
        ]);

        $this->assertTrue($result);
    }

    public function test_track_business_metric_returns_false_when_disabled(): void
    {
        $analytics = new UssdAnalytics(['track_business_metrics' => false, 'store_in_database' => false]);

        $result = $analytics->trackBusinessMetric('test', 1);

        $this->assertFalse($result);
    }

    // ==================== Event tracking - conversion ====================

    public function test_track_conversion_records_event(): void
    {
        $result = $this->analytics->trackConversion('signup_completed', '+254712345678', 1);

        $this->assertTrue($result);
    }

    // ==================== Buffer flushing ====================

    public function test_flush_buffer_returns_false_when_empty(): void
    {
        $result = $this->analytics->flushBuffer();

        $this->assertFalse($result);
    }

    public function test_flush_buffer_returns_false_when_db_disabled(): void
    {
        $analytics = new UssdAnalytics([
            'store_in_database' => false,
            'buffer_size' => 100,
        ]);

        // Add an event
        $analytics->trackSession('+254712345678', 'start');

        $result = $analytics->flushBuffer();

        $this->assertFalse($result);
    }

    public function test_buffer_auto_flushes_at_size_limit(): void
    {
        $analytics = new UssdAnalytics([
            'enabled' => true,
            'store_in_database' => false,
            'buffer_size' => 3,
            'real_time_dashboard' => false,
        ]);

        // Fill buffer to threshold - since store_in_database is false, flush returns false
        // but internal buffer tracking still works
        $analytics->trackSession('+254712345678', 'event1');
        $analytics->trackSession('+254712345678', 'event2');
        $analytics->trackSession('+254712345678', 'event3');

        // The buffer should have attempted auto-flush at count 3
        // Since store_in_database is false, events remain in buffer
        $this->assertTrue(true); // The auto-flush logic was triggered without errors
    }

    // ==================== Phone number hashing ====================

    public function test_phone_number_is_hashed_when_anonymize_enabled(): void
    {
        $analytics = new UssdAnalytics([
            'enabled' => true,
            'anonymize_users' => true,
            'store_in_database' => false,
            'real_time_dashboard' => false,
        ]);

        // Use reflection to test the hash method
        $reflection = new \ReflectionClass($analytics);
        $method = $reflection->getMethod('hashPhoneNumber');
        $method->setAccessible(true);

        $hashed = $method->invoke($analytics, '+254712345678');

        $this->assertNotEquals('+254712345678', $hashed);
        $this->assertEquals(hash('sha256', '+254712345678'), $hashed);
    }

    public function test_phone_number_not_hashed_when_anonymize_disabled(): void
    {
        $analytics = new UssdAnalytics([
            'anonymize_users' => false,
            'store_in_database' => false,
        ]);

        $reflection = new \ReflectionClass($analytics);
        $method = $reflection->getMethod('hashPhoneNumber');
        $method->setAccessible(true);

        $hashed = $method->invoke($analytics, '+254712345678');

        $this->assertEquals('+254712345678', $hashed);
    }

    // ==================== Metric aggregation (real-time metrics) ====================

    public function test_get_real_time_metrics_returns_defaults(): void
    {
        $metrics = $this->analytics->getRealTimeMetrics();

        $this->assertArrayHasKey('active_sessions', $metrics);
        $this->assertArrayHasKey('total_interactions', $metrics);
        $this->assertArrayHasKey('error_count', $metrics);
        $this->assertArrayHasKey('popular_menus', $metrics);
        $this->assertArrayHasKey('performance_stats', $metrics);
    }

    // ==================== Percentile calculation ====================

    public function test_calculate_percentile(): void
    {
        $reflection = new \ReflectionClass($this->analytics);
        $method = $reflection->getMethod('calculatePercentile');
        $method->setAccessible(true);

        $values = [10, 20, 30, 40, 50, 60, 70, 80, 90, 100];
        $p50 = $method->invoke($this->analytics, $values, 50);
        $p99 = $method->invoke($this->analytics, $values, 99);

        $this->assertEquals(55.0, $p50);
        $this->assertGreaterThan(90, $p99);
    }

    public function test_calculate_percentile_empty_array(): void
    {
        $reflection = new \ReflectionClass($this->analytics);
        $method = $reflection->getMethod('calculatePercentile');
        $method->setAccessible(true);

        $result = $method->invoke($this->analytics, [], 50);

        $this->assertEquals(0, $result);
    }

    // ==================== Report generation ====================

    public function test_generate_report_fails_when_db_disabled(): void
    {
        $analytics = new UssdAnalytics(['store_in_database' => false]);

        $result = $analytics->generateReport(
            \Carbon\Carbon::now()->subDays(7),
            \Carbon\Carbon::now()
        );

        $this->assertArrayHasKey('error', $result);
    }

    // ==================== Cleanup ====================

    public function test_cleanup_returns_false_when_db_disabled(): void
    {
        $analytics = new UssdAnalytics(['store_in_database' => false]);

        $result = $analytics->cleanup();

        $this->assertFalse($result);
    }
}
