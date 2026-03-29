<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Tests\Unit;

use Moffhub\Ussd\Security\UssdAuditLogger;
use Moffhub\Ussd\Tests\TestCase;

class UssdAuditLoggerTest extends TestCase
{
    private UssdAuditLogger $logger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logger = new UssdAuditLogger([
            'enabled' => true,
            'log_level' => 'info',
            'store_in_database' => false,
            'retention_days' => 90,
            'sensitive_fields' => ['password', 'pin', 'account_number'],
            'log_successful_actions' => true,
            'log_failed_actions' => true,
            'log_security_events' => true,
            'log_performance_metrics' => true,
            'channels' => ['stack'],
        ]);
    }

    // ==================== Security event logging ====================

    public function test_log_security_records_event(): void
    {
        $result = $this->logger->logSecurity('auth_failure', '+254712345678', [
            'attempts' => 3,
            'ip' => '192.168.1.1',
        ]);

        $this->assertTrue($result);
    }

    public function test_log_security_disabled_returns_false(): void
    {
        $logger = new UssdAuditLogger([
            'log_security_events' => false,
            'store_in_database' => false,
        ]);

        $result = $logger->logSecurity('test_event', '+254712345678');

        $this->assertFalse($result);
    }

    // ==================== Log levels ====================

    public function test_log_action_with_info_level(): void
    {
        $result = $this->logger->logAction('menu_access', '+254712345678', ['menu' => 'main'], 'info');

        $this->assertTrue($result);
    }

    public function test_log_action_with_warning_level(): void
    {
        $result = $this->logger->logAction('suspicious_access', '+254712345678', [], 'warning');

        $this->assertTrue($result);
    }

    public function test_log_error_uses_error_level(): void
    {
        $error = new \Exception('Test error', 500);
        $result = $this->logger->logError($error, '+254712345678', ['context' => 'payment']);

        $this->assertTrue($result);
    }

    // ==================== Log context data ====================

    public function test_log_action_includes_context(): void
    {
        $result = $this->logger->logAction('user_action', '+254712345678', [
            'menu' => 'payment',
            'amount' => 100,
            'currency' => 'KES',
        ]);

        $this->assertTrue($result);
    }

    public function test_log_auth_success(): void
    {
        $result = $this->logger->logAuth('login', '+254712345678', true, ['method' => 'pin']);

        $this->assertTrue($result);
    }

    public function test_log_auth_failure(): void
    {
        $result = $this->logger->logAuth('login', '+254712345678', false, ['method' => 'pin']);

        $this->assertTrue($result);
    }

    // ==================== Disabled logger ====================

    public function test_disabled_logger_returns_false(): void
    {
        $logger = new UssdAuditLogger([
            'enabled' => false,
            'store_in_database' => false,
        ]);

        $this->assertFalse($logger->logAction('test', '+254712345678'));
    }

    // ==================== Data access logging ====================

    public function test_log_data_access(): void
    {
        $result = $this->logger->logDataAccess('user_profile', '+254712345678', 'read', [
            'fields_accessed' => ['name', 'balance'],
        ]);

        $this->assertTrue($result);
    }

    // ==================== Transaction logging ====================

    public function test_log_transaction(): void
    {
        $result = $this->logger->logTransaction('payment', '+254712345678', 500.00, [
            'reference' => 'TXN001',
            'recipient' => '+254711111111',
        ]);

        $this->assertTrue($result);
    }

    // ==================== Performance logging ====================

    public function test_log_performance(): void
    {
        $result = $this->logger->logPerformance('menu_render', '+254712345678', 150, [
            'menu' => 'payment',
        ]);

        $this->assertTrue($result);
    }

    public function test_log_performance_disabled_returns_false(): void
    {
        $logger = new UssdAuditLogger([
            'log_performance_metrics' => false,
            'store_in_database' => false,
        ]);

        $result = $logger->logPerformance('test', '+254712345678', 100);

        $this->assertFalse($result);
    }

    // ==================== Session logging ====================

    public function test_log_session(): void
    {
        $result = $this->logger->logSession('start', '+254712345678', 'sess_123', [
            'provider' => 'safaricom',
        ]);

        $this->assertTrue($result);
    }

    // ==================== Sensitive data masking ====================

    public function test_sensitive_fields_are_masked(): void
    {
        $reflection = new \ReflectionClass($this->logger);
        $method = $reflection->getMethod('sanitizeDetails');
        $method->setAccessible(true);

        $details = [
            'name' => 'John',
            'password' => 'secret123',
            'pin' => '1234',
            'account_number' => 'ACC123456',
        ];

        $sanitized = $method->invoke($this->logger, $details);

        $this->assertEquals('John', $sanitized['name']);
        $this->assertNotEquals('secret123', $sanitized['password']);
        $this->assertStringContainsString('*', $sanitized['password']);
        $this->assertStringContainsString('*', $sanitized['account_number']);
    }

    public function test_short_sensitive_values_fully_masked(): void
    {
        $reflection = new \ReflectionClass($this->logger);
        $method = $reflection->getMethod('maskSensitiveData');
        $method->setAccessible(true);

        $result = $method->invoke($this->logger, '12');

        $this->assertEquals('***', $result);
    }

    // ==================== Phone number sanitization ====================

    public function test_phone_number_is_hashed(): void
    {
        $reflection = new \ReflectionClass($this->logger);
        $method = $reflection->getMethod('sanitizePhoneNumber');
        $method->setAccessible(true);

        $result = $method->invoke($this->logger, '+254712345678');

        $this->assertNotEquals('+254712345678', $result);
        $this->assertEquals(hash('sha256', '+254712345678'), $result);
    }

    public function test_empty_phone_number_returns_null(): void
    {
        $reflection = new \ReflectionClass($this->logger);
        $method = $reflection->getMethod('sanitizePhoneNumber');
        $method->setAccessible(true);

        $result = $method->invoke($this->logger, '');

        $this->assertNull($result);
    }

    // ==================== Security severity ====================

    public function test_high_severity_events(): void
    {
        $reflection = new \ReflectionClass($this->logger);
        $method = $reflection->getMethod('calculateSecuritySeverity');
        $method->setAccessible(true);

        $this->assertEquals('high', $method->invoke($this->logger, 'auth_failure', []));
        $this->assertEquals('high', $method->invoke($this->logger, 'rate_limit_exceeded', []));
        $this->assertEquals('high', $method->invoke($this->logger, 'unauthorized_access', []));
    }

    public function test_medium_severity_events(): void
    {
        $reflection = new \ReflectionClass($this->logger);
        $method = $reflection->getMethod('calculateSecuritySeverity');
        $method->setAccessible(true);

        $this->assertEquals('medium', $method->invoke($this->logger, 'invalid_input', []));
        $this->assertEquals('medium', $method->invoke($this->logger, 'session_timeout', []));
    }

    public function test_low_severity_events(): void
    {
        $reflection = new \ReflectionClass($this->logger);
        $method = $reflection->getMethod('calculateSecuritySeverity');
        $method->setAccessible(true);

        $this->assertEquals('low', $method->invoke($this->logger, 'normal_event', []));
    }

    // ==================== Performance classification ====================

    public function test_performance_classification(): void
    {
        $reflection = new \ReflectionClass($this->logger);
        $method = $reflection->getMethod('classifyPerformance');
        $method->setAccessible(true);

        $this->assertEquals('excellent', $method->invoke($this->logger, 50));
        $this->assertEquals('good', $method->invoke($this->logger, 200));
        $this->assertEquals('acceptable', $method->invoke($this->logger, 700));
        $this->assertEquals('slow', $method->invoke($this->logger, 2000));
        $this->assertEquals('very_slow', $method->invoke($this->logger, 5000));
    }

    // ==================== Amount sanitization ====================

    public function test_sanitize_numeric_amount(): void
    {
        $reflection = new \ReflectionClass($this->logger);
        $method = $reflection->getMethod('sanitizeAmount');
        $method->setAccessible(true);

        $result = $method->invoke($this->logger, 100.567);
        $this->assertEquals(101.0, $result);
    }

    public function test_sanitize_non_numeric_amount(): void
    {
        $reflection = new \ReflectionClass($this->logger);
        $method = $reflection->getMethod('sanitizeAmount');
        $method->setAccessible(true);

        $result = $method->invoke($this->logger, 'invalid');
        $this->assertEquals('invalid', $result);
    }

    // ==================== Cleanup ====================

    public function test_cleanup_returns_false_when_db_disabled(): void
    {
        $result = $this->logger->cleanup();

        $this->assertFalse($result);
    }

    // ==================== Stats ====================

    public function test_get_stats_returns_error_when_db_disabled(): void
    {
        $stats = $this->logger->getStats();

        $this->assertArrayHasKey('error', $stats);
    }
}
