<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Services;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UssdDatabaseService
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'enable_database_logging' => true,
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
        ], $config);
    }

    public function saveRateLimit(string $phoneNumber, string $action, array $requestTimestamps, ?Carbon $blockedUntil = null): bool
    {
        if (! $this->config['enable_database_logging']) {
            return false;
        }

        try {
            $data = [
                'phone_number' => $this->hashPhoneNumber($phoneNumber),
                'action' => $action,
                'request_timestamps' => json_encode($requestTimestamps),
                'updated_at' => now(),
                'created_at' => DB::raw('COALESCE(created_at, NOW())'),
            ];

            if ($blockedUntil) {
                $data['blocked_until'] = $blockedUntil;
                $data['violation_count'] = DB::raw('COALESCE(violation_count, 0) + 1');
                $data['last_violation'] = now();
            }

            DB::table('ussd_rate_limits')->updateOrInsert(
                ['phone_number' => $this->hashPhoneNumber($phoneNumber), 'action' => $action],
                $data
            );

            return true;
        } catch (Exception $e) {
            Log::error('Failed to save rate limit data', [
                'error' => $e->getMessage(),
                'phone_number' => $phoneNumber,
                'action' => $action,
            ]);

            return false;
        }
    }

    public function saveSecurityEvent(
        string $eventType,
        ?string $phoneNumber = null,
        array $eventData = [],
        string $severity = 'low',
        ?string $sessionId = null
    ): bool {
        if (!$this->config['enable_database_logging']) {
            return false;
        }

        try {
            DB::table('ussd_security_events')->insert([
                'event_type' => $eventType,
                'phone_number' => $phoneNumber ? $this->hashPhoneNumber($phoneNumber) : null,
                'session_id' => $sessionId ?? request()->input('sessionId'),
                'ip_address' => request()->ip(),
                'severity' => $severity,
                'event_data' => json_encode(array_merge($eventData, [
                    'timestamp' => now()->toISOString(),
                    'user_agent' => request()->userAgent(),
                    'request_id' => request()->header('X-Request-ID') ?? uniqid(),
                ])),
                'status' => 'open',
                'detected_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Failed to save security event', [
                'error' => $e->getMessage(),
                'event_type' => $eventType,
                'phone_number' => $phoneNumber,
            ]);

            return false;
        }
    }

    public function saveUserSession(
        string $sessionId,
        string $phoneNumber,
        string $currentMenu,
        array $sessionData,
        ?Carbon $startedAt = null,
        bool $completed = false
    ): bool {
        if (! $this->config['enable_database_logging']) {
            return false;
        }

        try {
            $userJourney = $this->extractUserJourneyFromSessionData($sessionData);
            $totalInteractions = $sessionData['access_count'] ?? 0;

            DB::table('ussd_user_sessions')->updateOrInsert(
                ['session_id' => $sessionId],
                [
                    'phone_number' => $this->hashPhoneNumber($phoneNumber),
                    'current_menu' => $currentMenu,
                    'session_data' => json_encode($sessionData),
                    'started_at' => $startedAt ?? now(),
                    'last_activity' => now(),
                    'total_interactions' => $totalInteractions,
                    'user_journey' => json_encode($userJourney),
                    'completed' => $completed,
                    'updated_at' => now(),
                    'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                ]
            );

            return true;
        } catch (Exception $e) {
            Log::error('Failed to save user session', [
                'error' => $e->getMessage(),
                'session_id' => $sessionId,
                'phone_number' => $phoneNumber,
            ]);

            return false;
        }
    }

    public function saveSessionAnalytics(
        string $eventType,
        ?string $phoneNumber = null,
        ?string $sessionId = null,
        array $data = []
    ): bool {
        if (! $this->config['enable_database_logging']) {
            return false;
        }

        try {
            DB::table('ussd_analytics')->insert([
                'event_type' => $eventType,
                'phone_number' => $phoneNumber ? $this->hashPhoneNumber($phoneNumber) : null,
                'session_id' => $sessionId ?? request()->input('sessionId'),
                'data' => json_encode(array_merge($data, [
                    'timestamp' => now()->toISOString(),
                    'request_id' => request()->header('X-Request-ID') ?? uniqid(),
                ])),
                'timestamp' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Failed to save session analytics', [
                'error' => $e->getMessage(),
                'event_type' => $eventType,
                'phone_number' => $phoneNumber,
            ]);

            return false;
        }
    }

    public function saveRecoveryLog(
        string $phoneNumber,
        string $sessionId,
        string $recoveryType,
        array $originalSessionData,
        string $recoveryReason,
        array $recoveryContext = [],
        string $recoveryMethod = 'automatic'
    ): int {
        if (! $this->config['enable_database_logging']) {
            return 0;
        }

        try {
            return DB::table('ussd_session_recovery_logs')->insertGetId([
                'phone_number' => $this->hashPhoneNumber($phoneNumber),
                'session_id' => $sessionId,
                'recovery_type' => $recoveryType,
                'original_session_data' => json_encode($originalSessionData),
                'recovery_context' => json_encode($recoveryContext),
                'recovery_reason' => $recoveryReason,
                'recovery_method' => $recoveryMethod,
                'attempted_at' => now(),
                'success' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Exception $e) {
            Log::error('Failed to save recovery log', [
                'error' => $e->getMessage(),
                'phone_number' => $phoneNumber,
                'recovery_type' => $recoveryType,
            ]);

            return 0;
        }
    }

    public function completeRecoveryLog(int $logId, bool $success, array $recoveredData = [], ?string $errorMessage = null): bool
    {
        if ($logId === 0 || ! $this->config['enable_database_logging']) {
            return false;
        }

        try {
            DB::table('ussd_session_recovery_logs')
                ->where('id', $logId)
                ->update([
                    'recovered_session_data' => json_encode($recoveredData),
                    'success' => $success,
                    'completed_at' => now(),
                    'error_message' => $errorMessage,
                    'updated_at' => now(),
                ]);

            return true;
        } catch (Exception $e) {
            Log::error('Failed to complete recovery log', [
                'error' => $e->getMessage(),
                'log_id' => $logId,
            ]);

            return false;
        }
    }

    public function savePerformanceMetrics(
        string $action,
        int $durationMs,
        ?string $menuName = null,
        ?string $phoneNumber = null,
        ?string $sessionId = null,
        array $metadata = []
    ): bool {
        if (! $this->config['enable_database_logging']) {
            return false;
        }

        try {
            DB::table('ussd_performance_metrics')->insert([
                'action' => $action,
                'menu_name' => $menuName,
                'duration_ms' => $durationMs,
                'memory_usage' => $metadata['memory_usage'] ?? memory_get_usage(true),
                'phone_number' => $phoneNumber ? $this->hashPhoneNumber($phoneNumber) : null,
                'session_id' => $sessionId ?? request()->input('sessionId'),
                'metadata' => json_encode($metadata),
                'timestamp' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Failed to save performance metrics', [
                'error' => $e->getMessage(),
                'action' => $action,
            ]);

            return false;
        }
    }

    public function saveBusinessMetrics(
        string $metricName,
        string|int $metricValue,
        string $metricCategory = 'general',
        ?string $phoneNumber = null,
        ?string $sessionId = null,
        array $dimensions = [],
        array $metadata = []
    ): bool {
        if (! $this->config['enable_database_logging']) {
            return false;
        }

        try {
            DB::table('ussd_business_metrics')->insert([
                'metric_name' => $metricName,
                'metric_category' => $metricCategory,
                'metric_value' => is_numeric($metricValue) ? $metricValue : 0,
                'phone_number' => $phoneNumber ? $this->hashPhoneNumber($phoneNumber) : null,
                'session_id' => $sessionId ?? request()->input('sessionId'),
                'dimensions' => json_encode($dimensions),
                'metadata' => json_encode($metadata),
                'recorded_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Failed to save business metrics', [
                'error' => $e->getMessage(),
                'metric_name' => $metricName,
            ]);

            return false;
        }
    }

    public function updateMenuStatistics(
        string $menuName,
        ?string $optionSelected = null,
        bool $completed = false,
        bool $droppedOff = false,
        float $timeSpent = 0,
        array $userSegments = []
    ): bool {
        if (! $this->config['enable_database_logging']) {
            return false;
        }

        try {
            $today = now()->toDateString();

            $existing = DB::table('ussd_menu_statistics')
                ->where('menu_name', $menuName)
                ->where('option_selected', $optionSelected)
                ->where('date', $today)
                ->first();

            if ($existing) {
                $updates = [
                    'access_count' => $existing->access_count + 1,
                    'updated_at' => now(),
                ];

                if ($completed) {
                    $updates['completion_count'] = $existing->completion_count + 1;
                }

                if ($droppedOff) {
                    $updates['drop_off_count'] = $existing->drop_off_count + 1;
                }

                if ($timeSpent > 0) {
                    $totalTime = ($existing->avg_time_spent * $existing->access_count) + $timeSpent;
                    $updates['avg_time_spent'] = $totalTime / ($existing->access_count + 1);
                }

                DB::table('ussd_menu_statistics')
                    ->where('id', $existing->id)
                    ->update($updates);
            } else {
                DB::table('ussd_menu_statistics')->insert([
                    'menu_name' => $menuName,
                    'option_selected' => $optionSelected,
                    'access_count' => 1,
                    'completion_count' => $completed ? 1 : 0,
                    'drop_off_count' => $droppedOff ? 1 : 0,
                    'avg_time_spent' => $timeSpent,
                    'user_segments' => json_encode($userSegments),
                    'date' => $today,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return true;
        } catch (Exception $e) {
            Log::error('Failed to update menu statistics', [
                'error' => $e->getMessage(),
                'menu_name' => $menuName,
            ]);

            return false;
        }
    }

    public function cleanupOldRecords(): array
    {
        $results = [];

        foreach ($this->config['retention_days'] as $table => $retentionDays) {
            try {
                $cutoffDate = Carbon::now()->subDays($retentionDays);
                $tableName = $this->getTableName($table);
                $timestampColumn = $this->getTimestampColumn($table);

                $deleted = DB::table($tableName)
                    ->where($timestampColumn, '<', $cutoffDate)
                    ->delete();

                $results[$table] = [
                    'deleted' => $deleted,
                    'cutoff_date' => $cutoffDate->toDateString(),
                ];

                Log::info("Cleaned up old records from {$tableName}", [
                    'deleted_records' => $deleted,
                    'cutoff_date' => $cutoffDate->toDateString(),
                ]);
            } catch (Exception $e) {
                $results[$table] = [
                    'error' => $e->getMessage(),
                ];
                Log::error("Failed to cleanup {$table} table", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    public function getDashboardStats(?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $startDate = $startDate ?? Carbon::now()->subDays(7);
        $endDate = $endDate ?? Carbon::now();

        return [
            'rate_limits' => $this->getRateLimitStats($startDate, $endDate),
            'security_events' => $this->getSecurityEventStats($startDate, $endDate),
            'user_sessions' => $this->getUserSessionStats($startDate, $endDate),
            'analytics' => $this->getAnalyticsStats($startDate, $endDate),
            'recovery_logs' => $this->getRecoveryLogStats($startDate, $endDate),
            'performance' => $this->getPerformanceStats($startDate, $endDate),
            'business_metrics' => $this->getBusinessMetricsStats($startDate, $endDate),
        ];
    }

    protected function getRateLimitStats(Carbon $startDate, Carbon $endDate): array
    {
        try {
            $query = DB::table('ussd_rate_limits')
                ->whereBetween('updated_at', [$startDate, $endDate]);

            return [
                'total_rate_limited_users' => $query->count(),
                'currently_blocked' => DB::table('ussd_rate_limits')
                    ->where('blocked_until', '>', now())
                    ->count(),
                'violations_by_day' => $query->selectRaw('DATE(last_violation) as date, SUM(violation_count) as violations')
                    ->whereNotNull('last_violation')
                    ->groupBy('date')
                    ->orderBy('date')
                    ->pluck('violations', 'date')
                    ->toArray(),
            ];
        } catch (Exception $e) {
            Log::error('Failed to get rate limit stats', ['error' => $e->getMessage()]);

            return ['error' => 'Failed to fetch rate limit stats'];
        }
    }

    protected function getSecurityEventStats(Carbon $startDate, Carbon $endDate): array
    {
        try {
            $query = DB::table('ussd_security_events')
                ->whereBetween('detected_at', [$startDate, $endDate]);

            return [
                'total_events' => $query->count(),
                'by_severity' => $query->selectRaw('severity, count(*) as count')
                    ->groupBy('severity')
                    ->pluck('count', 'severity')
                    ->toArray(),
                'by_type' => $query->selectRaw('event_type, count(*) as count')
                    ->groupBy('event_type')
                    ->pluck('count', 'event_type')
                    ->toArray(),
                'open_events' => DB::table('ussd_security_events')
                    ->where('status', 'open')
                    ->count(),
            ];
        } catch (Exception $e) {
            Log::error('Failed to get security event stats', ['error' => $e->getMessage()]);

            return ['error' => 'Failed to fetch security event stats'];
        }
    }

    protected function getUserSessionStats(Carbon $startDate, Carbon $endDate): array
    {
        try {
            $query = DB::table('ussd_user_sessions')
                ->whereBetween('started_at', [$startDate, $endDate]);

            $totalSessions = $query->count();
            $completedSessions = (clone $query)->where('completed', true)->count();
            $activeSessions = DB::table('ussd_user_sessions')
                ->whereNull('ended_at')
                ->where('last_activity', '>', now()->subMinutes(30))
                ->count();

            return [
                'total_sessions' => $totalSessions,
                'completed_sessions' => $completedSessions,
                'active_sessions' => $activeSessions,
                'completion_rate' => $totalSessions > 0 ? ($completedSessions / $totalSessions) * 100 : 0,
                'avg_interactions' => $query->avg('total_interactions') ?? 0,
                'popular_menus' => $query->selectRaw('current_menu, count(*) as count')
                    ->whereNotNull('current_menu')
                    ->groupBy('current_menu')
                    ->orderByDesc('count')
                    ->limit(5)
                    ->pluck('count', 'current_menu')
                    ->toArray(),
            ];
        } catch (Exception $e) {
            Log::error('Failed to get user session stats', ['error' => $e->getMessage()]);

            return ['error' => 'Failed to fetch user session stats'];
        }
    }

    protected function getAnalyticsStats(Carbon $startDate, Carbon $endDate): array
    {
        try {
            $query = DB::table('ussd_analytics')
                ->whereBetween('timestamp', [$startDate, $endDate]);

            return [
                'total_events' => $query->count(),
                'unique_users' => $query->whereNotNull('phone_number')
                    ->distinct('phone_number')
                    ->count(),
                'by_event_type' => $query->selectRaw('event_type, count(*) as count')
                    ->groupBy('event_type')
                    ->pluck('count', 'event_type')
                    ->toArray(),
                'daily_activity' => $query->selectRaw('DATE(timestamp) as date, count(*) as count')
                    ->groupBy('date')
                    ->orderBy('date')
                    ->pluck('count', 'date')
                    ->toArray(),
            ];
        } catch (Exception $e) {
            Log::error('Failed to get analytics stats', ['error' => $e->getMessage()]);

            return ['error' => 'Failed to fetch analytics stats'];
        }
    }

    protected function getRecoveryLogStats(Carbon $startDate, Carbon $endDate): array
    {
        try {
            $query = DB::table('ussd_session_recovery_logs')
                ->whereBetween('attempted_at', [$startDate, $endDate]);

            $totalAttempts = $query->count();
            $successfulRecoveries = (clone $query)->where('success', true)->count();

            return [
                'total_recovery_attempts' => $totalAttempts,
                'successful_recoveries' => $successfulRecoveries,
                'success_rate' => $totalAttempts > 0 ? ($successfulRecoveries / $totalAttempts) * 100 : 0,
                'by_recovery_type' => $query->selectRaw('recovery_type, count(*) as count')
                    ->groupBy('recovery_type')
                    ->pluck('count', 'recovery_type')
                    ->toArray(),
                'by_recovery_method' => $query->selectRaw('recovery_method, count(*) as count')
                    ->groupBy('recovery_method')
                    ->pluck('count', 'recovery_method')
                    ->toArray(),
            ];
        } catch (Exception $e) {
            Log::error('Failed to get recovery log stats', ['error' => $e->getMessage()]);

            return ['error' => 'Failed to fetch recovery log stats'];
        }
    }

    protected function getPerformanceStats(Carbon $startDate, Carbon $endDate): array
    {
        try {
            $query = DB::table('ussd_performance_metrics')
                ->whereBetween('timestamp', [$startDate, $endDate]);

            return [
                'total_measurements' => $query->count(),
                'avg_duration' => $query->avg('duration_ms') ?? 0,
                'by_action' => $query->selectRaw('action, count(*) as count, avg(duration_ms) as avg_duration')
                    ->groupBy('action')
                    ->get()
                    ->pluck(['count' => 'count', 'avg_duration' => 'avg_duration'], 'action')
                    ->toArray(),
                'slow_operations' => $query->where('duration_ms', '>', 1000)
                    ->count(),
            ];
        } catch (Exception $e) {
            Log::error('Failed to get performance stats', ['error' => $e->getMessage()]);

            return ['error' => 'Failed to fetch performance stats'];
        }
    }

    protected function getBusinessMetricsStats(Carbon $startDate, Carbon $endDate): array
    {
        try {
            $query = DB::table('ussd_business_metrics')
                ->whereBetween('recorded_at', [$startDate, $endDate]);

            return [
                'total_metrics' => $query->count(),
                'by_category' => $query->selectRaw('metric_category, count(*) as count')
                    ->groupBy('metric_category')
                    ->pluck('count', 'metric_category')
                    ->toArray(),
                'by_metric_name' => $query->selectRaw('metric_name, count(*) as count, avg(metric_value) as avg_value')
                    ->groupBy('metric_name')
                    ->get()
                    ->pluck(['count' => 'count', 'avg_value' => 'avg_value'], 'metric_name')
                    ->toArray(),
            ];
        } catch (Exception $e) {
            Log::error('Failed to get business metrics stats', ['error' => $e->getMessage()]);

            return ['error' => 'Failed to fetch business metrics stats'];
        }
    }

    protected function extractUserJourneyFromSessionData(array $sessionData): array
    {
        $journey = [];

        if (isset($sessionData['menu_history']) && is_array($sessionData['menu_history'])) {
            foreach ($sessionData['menu_history'] as $historyItem) {
                $journey[] = [
                    'menu' => $historyItem['menu'] ?? null,
                    'timestamp' => $historyItem['timestamp'] ?? null,
                    'step' => $historyItem['step'] ?? null,
                ];
            }
        }

        if (isset($sessionData['current_menu'])) {
            $journey[] = [
                'menu' => $sessionData['current_menu'],
                'timestamp' => now()->toISOString(),
                'step' => $sessionData['step'] ?? 0,
                'current' => true,
            ];
        }

        return $journey;
    }

    protected function hashPhoneNumber(string $phoneNumber): string
    {
        if (! $this->config['anonymize_phone_numbers']) {
            return $phoneNumber;
        }

        return hash('sha256', $phoneNumber);
    }

    protected function getTableName(string $dataType): string
    {
        return match ($dataType) {
            'rate_limits' => 'ussd_rate_limits',
            'security_events' => 'ussd_security_events',
            'analytics' => 'ussd_analytics',
            'user_sessions' => 'ussd_user_sessions',
            'recovery_logs' => 'ussd_session_recovery_logs',
            'performance_metrics' => 'ussd_performance_metrics',
            'business_metrics' => 'ussd_business_metrics',
            default => "ussd_{$dataType}",
        };
    }

    protected function getTimestampColumn(string $dataType): string
    {
        return match ($dataType) {
            'rate_limits' => 'updated_at',
            'security_events' => 'detected_at',
            'analytics' => 'timestamp',
            'user_sessions' => 'started_at',
            'recovery_logs' => 'attempted_at',
            'performance_metrics' => 'timestamp',
            'business_metrics' => 'recorded_at',
            default => 'created_at',
        };
    }
}
