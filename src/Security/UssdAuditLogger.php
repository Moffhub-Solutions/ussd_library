<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Security;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Moffhub\Ussd\Interfaces\AuditLoggerInterface;
use Moffhub\Ussd\Services\UssdDatabaseService;
use Throwable;

class UssdAuditLogger implements AuditLoggerInterface
{
    protected array $config;

    protected array $context;

    protected ?UssdDatabaseService $databaseService = null;

    public function __construct(array $config = [])
    {
        $defaultChannel = config('ussd.logging.channel') ?? config('logging.default', 'stack');

        $this->config = array_merge([
            'enabled' => true,
            'log_level' => 'info',
            'store_in_database' => true,
            'table_name' => 'ussd_audit_logs',
            'retention_days' => 90,
            'sensitive_fields' => ['password', 'pin', 'account_number'],
            'log_successful_actions' => true,
            'log_failed_actions' => true,
            'log_security_events' => true,
            'log_performance_metrics' => false,
            'channels' => [$defaultChannel],
        ], $config);

        $this->context = [
            'service' => 'ussd',
            'version' => '1.0',
            'server' => gethostname(),
        ];
    }

    public function setDatabaseService(UssdDatabaseService $databaseService): void
    {
        $this->databaseService = $databaseService;
    }

    public function logAction(string $action, string $phoneNumber, array $details = [], string $level = 'info'): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $logData = $this->buildLogData($action, $phoneNumber, $details, 'action');

        return $this->writeLog($logData, $level);
    }

    public function logSecurity(string $event, string $phoneNumber, array $details = [], string $level = 'warning'): bool
    {
        if (! $this->config['log_security_events']) {
            return false;
        }

        $logData = $this->buildLogData($event, $phoneNumber, $details, 'security');
        $logData['severity'] = $this->calculateSecuritySeverity($event, $details);

        $this->saveSecurityEventToDatabase($event, $phoneNumber, $details, $logData['severity']);

        return $this->writeLog($logData, $level);
    }

    protected function saveSecurityEventToDatabase(string $eventType, string $phoneNumber, array $details, string $severity): void
    {
        if ($this->databaseService instanceof UssdDatabaseService) {
            $this->databaseService->saveSecurityEvent($eventType, $phoneNumber, $details, $severity);

            return;
        }

        try {
            DB::table('ussd_security_events')->insert([
                'event_type' => $eventType,
                'phone_number' => $phoneNumber,
                'session_id' => request()->input('sessionId'),
                'ip_address' => request()->ip(),
                'severity' => $severity,
                'event_data' => json_encode(array_merge($details, [
                    'timestamp' => now()->toISOString(),
                    'user_agent' => request()->userAgent(),
                ])),
                'status' => 'open',
                'detected_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Exception $e) {
            Log::error('Failed to save security event to database', [
                'error' => $e->getMessage(),
                'event_type' => $eventType,
                'phone_number' => $phoneNumber,
            ]);
        }
    }

    public function logAuth(string $action, string $phoneNumber, bool $success = true, array $details = []): bool
    {
        $event = $success ? 'auth_success' : 'auth_failure';
        $level = $success ? 'info' : 'warning';

        $details['success'] = $success;
        $details['auth_action'] = $action;

        return $this->logSecurity($event, $phoneNumber, $details, $level);
    }

    public function logDataAccess(string $resource, string $phoneNumber, string $action = 'read', array $details = []): bool
    {
        $logData = $this->buildLogData('data_access', $phoneNumber, array_merge($details, [
            'resource' => $resource,
            'access_type' => $action,
        ]), 'data');

        return $this->writeLog($logData, 'info');
    }

    public function logTransaction(string $type, string $phoneNumber, float|string $amount, array $details = []): bool
    {
        $logData = $this->buildLogData('transaction', $phoneNumber, array_merge($details, [
            'transaction_type' => $type,
            'amount' => $this->sanitizeAmount($amount),
            'reference' => $details['reference'] ?? null,
        ]), 'transaction');

        return $this->writeLog($logData, 'info');
    }

    public function logError(Throwable $error, string $phoneNumber, array $details = []): bool
    {
        $errorDetails = [
            'error_message' => $error instanceof Exception ? $error->getMessage() : (string) $error,
            'error_code' => $error instanceof Exception ? $error->getCode() : null,
            'error_file' => $error instanceof Exception ? $error->getFile() : null,
            'error_line' => $error instanceof Exception ? $error->getLine() : null,
            'stack_trace' => $error instanceof Exception ? $error->getTraceAsString() : null,
        ];

        $logData = $this->buildLogData('error', $phoneNumber, array_merge($details, $errorDetails), 'error');

        return $this->writeLog($logData, 'error');
    }

    public function logPerformance(string $action, string $phoneNumber, int $duration, array $details = []): bool
    {
        if (! $this->config['log_performance_metrics']) {
            return false;
        }

        $logData = $this->buildLogData('performance', $phoneNumber, array_merge($details, [
            'action' => $action,
            'duration_ms' => $duration,
            'performance_tier' => $this->classifyPerformance($duration),
        ]), 'performance');

        return $this->writeLog($logData, 'info');
    }

    public function logSession(string $event, string $phoneNumber, string $sessionId, array $details = []): bool
    {
        $logData = $this->buildLogData('session_'.$event, $phoneNumber, array_merge($details, [
            'session_id' => $sessionId,
            'session_event' => $event,
        ]), 'session');

        return $this->writeLog($logData, 'info');
    }

    protected function buildLogData(string $action, string $phoneNumber, array $details, string $category): array
    {
        $request = request();

        return array_merge($this->context, [
            'timestamp' => Carbon::now()->toISOString(),
            'action' => $action,
            'category' => $category,
            'phone_number' => $this->sanitizePhoneNumber($phoneNumber),
            'session_id' => $request->input('sessionId'),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_id' => $request->header('X-Request-ID') ?? uniqid(),
            'details' => $this->sanitizeDetails($details),
            'metadata' => [
                'memory_usage' => memory_get_usage(true),
                'peak_memory' => memory_get_peak_usage(true),
                'execution_time' => defined('LARAVEL_START') ? (microtime(true) - LARAVEL_START) : null,
            ],
        ]);
    }

    protected function writeLog(array $logData, string $level): bool
    {
        try {
            foreach ($this->config['channels'] as $channel) {
                try {
                    Log::channel($channel)->{$level}($logData['action'], $logData);
                } catch (Throwable $channelException) {
                    error_log("[AUDIT LOGGER] Failed to log to channel '$channel': {$channelException->getMessage()}");
                }
            }

            if ($this->config['store_in_database']) {
                $this->storeInDatabase($logData);
            }

            return true;
        } catch (Throwable $e) {
            error_log('[AUDIT LOGGER] Failed to write audit log: '.$e->getMessage());

            return false;
        }
    }

    protected function storeInDatabase(array $logData): void
    {
        try {
            // Map severity to valid enum values: low, medium, high
            $severity = $logData['severity'] ?? 'low';
            if (! in_array($severity, ['low', 'medium', 'high'])) {
                $severity = 'low';
            }

            DB::table($this->config['table_name'])->insert([
                'timestamp' => Carbon::parse($logData['timestamp']),
                'action' => $logData['action'],
                'category' => $logData['category'],
                'phone_number' => $logData['phone_number'],
                'session_id' => $logData['session_id'],
                'ip_address' => $logData['ip_address'],
                'details' => json_encode($logData['details']),
                'metadata' => json_encode($logData['metadata']),
                'severity' => $severity,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Exception $e) {
            Log::error('Failed to store audit log in database', [
                'error' => $e->getMessage(),
                'table' => $this->config['table_name'],
            ]);
        }
    }

    protected function sanitizePhoneNumber(string $phoneNumber): ?string
    {
        if ($phoneNumber === '' || $phoneNumber === '0') {
            return null;
        }

        return hash('sha256', $phoneNumber);
    }

    protected function sanitizeDetails(array|string $details): array|string
    {
        if (! is_array($details)) {
            return $details;
        }

        $sanitized = [];

        foreach ($details as $key => $value) {
            if (in_array(strtolower((string) $key), $this->config['sensitive_fields'])) {
                $sanitized[$key] = $this->maskSensitiveData($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    protected function maskSensitiveData(string|int $value): string
    {
        if (is_string($value) && strlen($value) > 4) {
            return substr($value, 0, 2).str_repeat('*', strlen($value) - 4).substr($value, -2);
        }

        return '***';
    }

    protected function sanitizeAmount(string|float $amount): float|string
    {
        if (is_numeric($amount)) {
            return round(floatval($amount), 0);
        }

        return $amount;
    }

    protected function calculateSecuritySeverity(string $event, array $details): string
    {
        $highSeverityEvents = [
            'auth_failure',
            'rate_limit_exceeded',
            'suspicious_input',
            'unauthorized_access',
            'data_breach_attempt',
        ];

        $mediumSeverityEvents = [
            'invalid_input',
            'session_timeout',
            'multiple_failures',
        ];
        if (in_array($event, $highSeverityEvents)) {
            return 'high';
        }

        if (in_array($event, $mediumSeverityEvents)) {
            return 'medium';
        }

        return 'low';
    }

    protected function classifyPerformance(int $duration): string
    {
        if ($duration < 100) {
            return 'excellent';
        }
        if ($duration < 500) {
            return 'good';
        }
        if ($duration < 1000) {
            return 'acceptable';
        }
        if ($duration < 3000) {
            return 'slow';
        }

        return 'very_slow';
    }

    protected function isEnabled(): bool
    {
        return $this->config['enabled'] ?? true;
    }

    public function cleanup(): false|int
    {
        if (! $this->config['store_in_database']) {
            return false;
        }

        $cutoffDate = Carbon::now()->subDays($this->config['retention_days']);

        $deleted = DB::table($this->config['table_name'])
            ->where('timestamp', '<', $cutoffDate)
            ->delete();

        Log::info('Audit log cleanup completed', [
            'deleted_records' => $deleted,
            'cutoff_date' => $cutoffDate->toDateString(),
        ]);

        return $deleted;
    }

    public function getStats(?string $startDate = null, ?string $endDate = null): array
    {
        if (! $this->config['store_in_database']) {
            return ['error' => 'Database storage not enabled'];
        }

        $query = DB::table($this->config['table_name']);

        if ($startDate) {
            $query->where('timestamp', '>=', Carbon::parse($startDate));
        }

        if ($endDate) {
            $query->where('timestamp', '<=', Carbon::parse($endDate));
        }

        return [
            'total_logs' => $query->count(),
            'by_category' => $query->select('category', DB::raw('count(*) as count'))
                ->groupBy('category')
                ->pluck('count', 'category')
                ->toArray(),
            'by_severity' => $query->select('severity', DB::raw('count(*) as count'))
                ->groupBy('severity')
                ->pluck('count', 'severity')
                ->toArray(),
            'date_range' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
        ];
    }
}
