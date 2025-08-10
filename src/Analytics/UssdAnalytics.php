<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Analytics;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Moffhub\Ussd\Services\UssdDatabaseService;

class UssdAnalytics
{
    protected array $config;

    protected array $metricsBuffer = [];

    protected ?UssdDatabaseService $databaseService = null;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'enabled' => true,
            'store_in_database' => true,
            'table_name' => 'ussd_analytics',
            'buffer_size' => 100,
            'flush_interval' => 300, // 5 minutes
            'retention_days' => 365,
            'track_user_journey' => true,
            'track_performance' => true,
            'track_errors' => true,
            'track_business_metrics' => true,
            'anonymize_users' => true,
            'real_time_dashboard' => true,
        ], $config);
    }

    /**
     * Set database service for enhanced logging
     */
    public function setDatabaseService(UssdDatabaseService $databaseService): void
    {
        $this->databaseService = $databaseService;
    }

    public function trackMenuInteraction(string $phoneNumber, string $menuName, string $option, array $details = []): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $event = [
            'event_type' => 'menu_interaction',
            'phone_number' => $this->hashPhoneNumber($phoneNumber),
            'menu_name' => $menuName,
            'option_selected' => $option,
            'timestamp' => now(),
            'session_id' => $this->getCurrentSessionId(),
            'details' => $details,
        ];

        return $this->recordEvent($event);
    }

    public function trackUserJourney(string $phoneNumber, string $fromMenu, string $toMenu, string $action = 'navigate'): bool
    {
        if (! $this->config['track_user_journey']) {
            return false;
        }

        $event = [
            'event_type' => 'user_journey',
            'phone_number' => $this->hashPhoneNumber($phoneNumber),
            'from_menu' => $fromMenu,
            'to_menu' => $toMenu,
            'action' => $action,
            'timestamp' => now(),
            'session_id' => $this->getCurrentSessionId(),
        ];

        return $this->recordEvent($event);
    }

    public function trackSession(string $phoneNumber, string $event, array $details = []): bool
    {
        $sessionEvent = [
            'event_type' => 'session',
            'phone_number' => $this->hashPhoneNumber($phoneNumber),
            'session_event' => $event,
            'session_id' => $this->getCurrentSessionId(),
            'timestamp' => now(),
            'details' => $details,
        ];

        return $this->recordEvent($sessionEvent);
    }

    /**
     * Track performance metrics
     */
    public function trackPerformance(string $action, int|float $duration, array $details = []): bool
    {
        if (! $this->config['track_performance']) {
            return false;
        }

        $event = [
            'event_type' => 'performance',
            'action' => $action,
            'duration_ms' => $duration,
            'timestamp' => now(),
            'session_id' => $this->getCurrentSessionId(),
            'details' => array_merge($details, [
                'memory_usage' => memory_get_usage(true),
                'peak_memory' => memory_get_peak_usage(true),
            ]),
        ];

        $this->savePerformanceToDatabase($action, $duration, $details);

        return $this->recordEvent($event);
    }

    protected function savePerformanceToDatabase(string $action, int|float $duration, array $details): void
    {
        try {
            DB::table('ussd_performance_metrics')->insert([
                'action' => $action,
                'menu_name' => $details['menu'] ?? null,
                'duration_ms' => (int) $duration,
                'memory_usage' => $details['memory_usage'] ?? memory_get_usage(true),
                'phone_number' => request()->input('phoneNumber'),
                'session_id' => $this->getCurrentSessionId(),
                'metadata' => json_encode($details),
                'timestamp' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to save performance metrics to database', [
                'error' => $e->getMessage(),
                'action' => $action,
            ]);
        }
    }

    /**
     * Track errors and exceptions
     */
    public function trackError(Error $error, ?string $phoneNumber = null, array $context = []): bool
    {
        if (! $this->config['track_errors']) {
            return false;
        }

        $event = [
            'event_type' => 'error',
            'phone_number' => $phoneNumber ? $this->hashPhoneNumber($phoneNumber) : null,
            'error_type' => get_class($error),
            'error_message' => $error->getMessage(),
            'error_code' => $error->getCode(),
            'timestamp' => now(),
            'session_id' => $this->getCurrentSessionId(),
            'context' => $context,
        ];

        return $this->recordEvent($event);
    }

    /**
     * Track business metrics
     */
    public function trackBusinessMetric(string $metric, string|int|float $value, ?string $phoneNumber = null, array $details = []): bool
    {
        if (! $this->config['track_business_metrics']) {
            return false;
        }

        $event = [
            'event_type' => 'business_metric',
            'phone_number' => $phoneNumber ? $this->hashPhoneNumber($phoneNumber) : null,
            'metric_name' => $metric,
            'metric_value' => $value,
            'timestamp' => now(),
            'session_id' => $this->getCurrentSessionId(),
            'details' => $details,
        ];

        $this->saveBusinessMetricToDatabase($metric, $value, $phoneNumber, $details);

        return $this->recordEvent($event);
    }

    protected function saveBusinessMetricToDatabase(string $metricName, float|int|string $metricValue, ?string $phoneNumber, array $details): void
    {
        try {
            DB::table('ussd_business_metrics')->insert([
                'metric_name' => $metricName,
                'metric_category' => $details['category'] ?? 'general',
                'metric_value' => is_numeric($metricValue) ? $metricValue : 0,
                'phone_number' => $phoneNumber,
                'session_id' => $this->getCurrentSessionId(),
                'dimensions' => json_encode($details['dimensions'] ?? []),
                'metadata' => json_encode($details),
                'recorded_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to save business metric to database', [
                'error' => $e->getMessage(),
                'metric_name' => $metricName,
            ]);
        }
    }

    public function trackConversion(string $event, string $phoneNumber, string|int|null $value = null, array $details = []): bool
    {
        $conversionEvent = [
            'event_type' => 'conversion',
            'phone_number' => $this->hashPhoneNumber($phoneNumber),
            'conversion_event' => $event,
            'conversion_value' => $value,
            'timestamp' => now(),
            'session_id' => $this->getCurrentSessionId(),
            'details' => $details,
        ];

        return $this->recordEvent($conversionEvent);
    }

    /**
     * Record event to buffer and database
     */
    protected function recordEvent(array $event): bool
    {
        $this->metricsBuffer[] = $event;

        if ($this->shouldStoreCriticalEventImmediately($event)) {
            $this->storeEventInDatabase($event);
        }

        if (count($this->metricsBuffer) >= $this->config['buffer_size']) {
            $this->flushBuffer();
        }

        if ($this->config['real_time_dashboard']) {
            $this->updateRealTimeMetrics($event);
        }

        return true;
    }

    protected function storeEventInDatabase(array $event): bool
    {
        try {
            DB::table('ussd_analytics')->insert([
                'event_type' => $event['event_type'],
                'phone_number' => $event['phone_number'] ?? null,
                'session_id' => $event['session_id'] ?? null,
                'data' => json_encode($event),
                'timestamp' => $event['timestamp'] ?? now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to store analytics event in database', [
                'error' => $e->getMessage(),
                'event_type' => $event['event_type'] ?? 'unknown',
            ]);

            return false;
        }
    }

    protected function shouldStoreCriticalEventImmediately(array $event): bool
    {
        $criticalEventTypes = ['error', 'security', 'transaction', 'auth_failure'];

        return in_array($event['event_type'], $criticalEventTypes);
    }

    public function flushBuffer(): bool
    {
        if (empty($this->metricsBuffer) || ! $this->config['store_in_database']) {
            return false;
        }

        try {
            $records = [];
            foreach ($this->metricsBuffer as $event) {
                $records[] = [
                    'event_type' => $event['event_type'],
                    'phone_number' => $event['phone_number'] ?? null,
                    'session_id' => $event['session_id'] ?? null,
                    'data' => json_encode($event),
                    'timestamp' => $event['timestamp'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            DB::table($this->config['table_name'])->insert($records);

            Log::info('Analytics buffer flushed', [
                'records_count' => count($records),
            ]);

            $this->metricsBuffer = [];

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to flush analytics buffer', [
                'error' => $e->getMessage(),
                'buffer_size' => count($this->metricsBuffer),
            ]);

            return false;
        }
    }

    /**
     * Update real-time metrics cache
     */
    protected function updateRealTimeMetrics(array $event): void
    {
        $cacheKey = 'ussd_real_time_metrics';
        $metrics = Cache::get($cacheKey, [
            'active_sessions' => 0,
            'total_interactions' => 0,
            'error_count' => 0,
            'popular_menus' => [],
            'performance_stats' => [],
            'last_updated' => now(),
        ]);

        // Update based on event type
        switch ($event['event_type']) {
            case 'session':
                if ($event['session_event'] === 'start') {
                    $metrics['active_sessions']++;
                } elseif ($event['session_event'] === 'end') {
                    $metrics['active_sessions'] = max(0, $metrics['active_sessions'] - 1);
                }
                break;

            case 'menu_interaction':
                $metrics['total_interactions']++;
                $menuName = $event['menu_name'];
                $metrics['popular_menus'][$menuName] = ($metrics['popular_menus'][$menuName] ?? 0) + 1;
                break;

            case 'error':
                $metrics['error_count']++;
                break;

            case 'performance':
                $action = $event['action'];
                if (! isset($metrics['performance_stats'][$action])) {
                    $metrics['performance_stats'][$action] = [
                        'count' => 0,
                        'total_duration' => 0,
                        'avg_duration' => 0,
                    ];
                }
                $metrics['performance_stats'][$action]['count']++;
                $metrics['performance_stats'][$action]['total_duration'] += $event['duration_ms'];
                $metrics['performance_stats'][$action]['avg_duration'] =
                    $metrics['performance_stats'][$action]['total_duration'] /
                    $metrics['performance_stats'][$action]['count'];
                break;
        }

        $metrics['last_updated'] = now();
        Cache::put($cacheKey, $metrics, 300); // Cache for 5 minutes
    }

    public function getRealTimeMetrics(): array
    {
        return Cache::get('ussd_real_time_metrics', [
            'active_sessions' => 0,
            'total_interactions' => 0,
            'error_count' => 0,
            'popular_menus' => [],
            'performance_stats' => [],
            'last_updated' => now(),
        ]);
    }

    public function generateReport(Carbon $startDate, Carbon $endDate, array $metrics = []): array
    {
        if (! $this->config['store_in_database']) {
            return ['error' => 'Database storage not enabled'];
        }

        $query = DB::table($this->config['table_name'])
            ->whereBetween('timestamp', [$startDate, $endDate]);

        $report = [
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'summary' => $this->generateSummaryStats($query),
            'user_behavior' => $this->analyzeUserBehavior($query),
            'performance' => $this->analyzePerformance($query),
            'errors' => $this->analyzeErrors($query),
            'business_metrics' => $this->analyzeBusinessMetrics($query),
            'trends' => $this->analyzeTrends($query, $startDate, $endDate),
        ];

        return $report;
    }

    public function getFunnelAnalysis(array $steps, string $startDate, string $endDate): array
    {
        if (! $this->config['store_in_database']) {
            return ['error' => 'Database storage not enabled'];
        }

        $query = DB::table($this->config['table_name'])
            ->whereBetween('timestamp', [$startDate, $endDate])
            ->where('event_type', 'menu_interaction');

        $funnel = [];
        $previousCount = null;

        foreach ($steps as $step) {
            $count = $query->where(DB::raw('JSON_EXTRACT(data, "$.menu_name")'), $step)->count();

            $funnel[$step] = [
                'count' => $count,
                'drop_off_rate' => $previousCount ? (($previousCount - $count) / $previousCount) * 100 : 0,
                'conversion_rate' => $previousCount ? ($count / $previousCount) * 100 : 100,
            ];

            $previousCount = $count;
        }

        return $funnel;
    }

    public function saveSessionData(string $phoneNumber, string $sessionId, array $sessionData): bool
    {
        try {
            $this->saveUserSessionToDatabase($phoneNumber, $sessionId, $sessionData);

            $this->trackSession($phoneNumber, 'session_data_saved', [
                'session_data_keys' => array_keys($sessionData),
                'data_size' => strlen(json_encode($sessionData) ?: ''),
                'current_menu' => $sessionData['current_menu'] ?? null,
                'step' => $sessionData['step'] ?? null,
                'session_duration' => $this->calculateSessionDuration($sessionData),
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to save session analytics data', [
                'error' => $e->getMessage(),
                'phone_number' => $phoneNumber,
                'session_id' => $sessionId,
            ]);

            return false;
        }
    }

    protected function saveUserSessionToDatabase(string $phoneNumber, string $sessionId, array $sessionData): void
    {
        try {
            $userJourney = $this->extractUserJourney($sessionData);
            $totalInteractions = $sessionData['access_count'] ?? 0;
            $startedAt = isset($sessionData['created_at']) ? Carbon::parse($sessionData['created_at']) : now();

            DB::table('ussd_user_sessions')->updateOrInsert(
                ['session_id' => $sessionId],
                [
                    'phone_number' => $phoneNumber,
                    'current_menu' => $sessionData['current_menu'] ?? null,
                    'session_data' => json_encode($sessionData),
                    'started_at' => $startedAt,
                    'last_activity' => now(),
                    'total_interactions' => $totalInteractions,
                    'user_journey' => json_encode($userJourney),
                    'completed' => $this->isSessionCompleted($sessionData),
                    'updated_at' => now(),
                    'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                ]
            );
        } catch (\Exception $e) {
            Log::error('Failed to save user session to database', [
                'error' => $e->getMessage(),
                'session_id' => $sessionId,
            ]);
        }
    }

    protected function extractUserJourney(array $sessionData): array
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

    protected function isSessionCompleted(array $sessionData): bool
    {
        if (isset($sessionData['session_flags']['completed'])) {
            return $sessionData['session_flags']['completed'];
        }

        if (isset($sessionData['form_data']) && ! empty($sessionData['form_data'])) {
            $formData = $sessionData['form_data'];
            $filledFields = array_filter($formData, function ($value) {
                return ! empty($value);
            });

            return count($filledFields) >= 3;
        }

        return false;
    }

    protected function calculateSessionDuration(array $sessionData): ?float
    {
        if (! isset($sessionData['created_at'])) {
            return null;
        }

        $createdAt = Carbon::parse($sessionData['created_at']);

        return Carbon::now()->diffInSeconds($createdAt);
    }

    public function logRecoveryAttempt(
        string $phoneNumber,
        string $sessionId,
        string $recoveryType,
        array $originalSessionData,
        string $recoveryReason,
        array $recoveryContext = []
    ): int {
        try {
            return DB::table('ussd_session_recovery_logs')->insertGetId([
                'phone_number' => $phoneNumber,
                'session_id' => $sessionId,
                'recovery_type' => $recoveryType,
                'original_session_data' => json_encode($originalSessionData),
                'recovery_context' => json_encode($recoveryContext),
                'recovery_reason' => $recoveryReason,
                'recovery_method' => 'automatic',
                'attempted_at' => now(),
                'success' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log recovery attempt to database', [
                'error' => $e->getMessage(),
                'phone_number' => $phoneNumber,
                'recovery_type' => $recoveryType,
            ]);

            return 0;
        }
    }

    public function completeRecoveryLog(int $logId, bool $success, array $recoveredData = [], ?string $errorMessage = null): void
    {
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
        } catch (\Exception $e) {
            Log::error('Failed to complete recovery log in database', [
                'error' => $e->getMessage(),
                'log_id' => $logId,
            ]);
        }
    }

    protected function hashPhoneNumber(string $phoneNumber): string
    {
        if (! $this->config['anonymize_users']) {
            return $phoneNumber;
        }

        return hash('sha256', $phoneNumber);
    }

    protected function getCurrentSessionId(): string
    {
        return request()->input('sessionId') ?? uniqid();
    }

    protected function calculatePercentile(array $values, float $percentile): float
    {
        if (empty($values)) {
            return 0;
        }

        $index = ($percentile / 100) * (count($values) - 1);
        $lower = floor($index);
        $upper = ceil($index);

        if ($lower == $upper) {
            return $values[$lower];
        }

        return $values[$lower] + ($values[$upper] - $values[$lower]) * ($index - $lower);
    }

    protected function generateSummaryStats(mixed $query): array
    {
        $clonedQuery = clone $query;

        return [
            'total_events' => $clonedQuery->count(),
            'unique_users' => $clonedQuery->whereNotNull('phone_number')->distinct('phone_number')->count(),
            'total_sessions' => $clonedQuery->where('event_type', 'session')->count(),
            'avg_session_duration' => $this->calculateAverageSessionDuration($query),
            'completion_rate' => $this->calculateCompletionRate($query),
        ];
    }

    protected function analyzeUserBehavior(mixed $query): array
    {
        $clonedQuery = clone $query;

        return [
            'popular_menus' => $clonedQuery->where('event_type', 'menu_interaction')
                ->select(DB::raw('JSON_EXTRACT(data, "$.menu_name") as menu_name'), DB::raw('count(*) as count'))
                ->groupBy('menu_name')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->pluck('count', 'menu_name')
                ->toArray(),
            'user_journey_patterns' => $this->analyzeUserJourneys($query),
            'drop_off_points' => $this->identifyDropOffPoints($query),
            'peak_usage_hours' => $this->analyzePeakUsage($query),
        ];
    }

    protected function analyzePerformance(mixed $query): array
    {
        $clonedQuery = clone $query;

        $performanceData = $clonedQuery->where('event_type', 'performance')
            ->select('data')
            ->get()
            ->map(function ($record) {
                return json_decode($record->data, true);
            });

        $actionStats = [];
        foreach ($performanceData as $data) {
            $action = $data['action'];
            if (! isset($actionStats[$action])) {
                $actionStats[$action] = [
                    'count' => 0,
                    'total_duration' => 0,
                    'durations' => [],
                ];
            }
            $actionStats[$action]['count']++;
            $actionStats[$action]['total_duration'] += $data['duration_ms'];
            $actionStats[$action]['durations'][] = $data['duration_ms'];
        }

        foreach ($actionStats as $action => &$stats) {
            sort($stats['durations']);
            $count = count($stats['durations']);
            $stats['avg_duration'] = $stats['total_duration'] / $stats['count'];
            $stats['p50'] = $this->calculatePercentile($stats['durations'], 50);
            $stats['p95'] = $this->calculatePercentile($stats['durations'], 95);
            $stats['p99'] = $this->calculatePercentile($stats['durations'], 99);
            unset($stats['durations']); // Remove raw data to save memory
        }

        return $actionStats;
    }

    protected function analyzeErrors(mixed $query): array
    {
        $clonedQuery = clone $query;

        return [
            'total_errors' => $clonedQuery->where('event_type', 'error')->count(),
            'error_types' => $clonedQuery->where('event_type', 'error')
                ->select(DB::raw('JSON_EXTRACT(data, "$.error_type") as error_type'), DB::raw('count(*) as count'))
                ->groupBy('error_type')
                ->orderBy('count', 'desc')
                ->pluck('count', 'error_type')
                ->toArray(),
            'error_rate' => $this->calculateErrorRate($query),
            'error_trends' => $this->analyzeErrorTrends($query),
        ];
    }

    protected function calculateAverageSessionDuration(mixed $query): int
    {
        return 0;
    }

    protected function calculateCompletionRate(mixed $query): int
    {
        return 0;
    }

    protected function analyzeUserJourneys(mixed $query): array
    {
        return [];
    }

    protected function identifyDropOffPoints(mixed $query): array
    {
        return [];
    }

    protected function analyzePeakUsage(mixed $query): array
    {
        return [];
    }

    protected function analyzeBusinessMetrics(mixed $query): array
    {
        return [];
    }

    protected function analyzeTrends(mixed $query, Carbon $startDate, Carbon $endDate): array
    {
        return [];
    }

    protected function calculateErrorRate(mixed $query): int
    {
        return 0;
    }

    protected function analyzeErrorTrends(mixed $query): array
    {
        return [];
    }

    protected function isEnabled(): bool
    {
        return $this->config['enabled'] ?? true;
    }

    public function cleanup(): bool|int
    {
        if (! $this->config['store_in_database']) {
            return false;
        }

        $cutoffDate = Carbon::now()->subDays($this->config['retention_days']);

        $deleted = DB::table($this->config['table_name'])
            ->where('timestamp', '<', $cutoffDate)
            ->delete();

        Log::info('Analytics cleanup completed', [
            'deleted_records' => $deleted,
            'cutoff_date' => $cutoffDate->toDateString(),
        ]);

        return $deleted;
    }
}
