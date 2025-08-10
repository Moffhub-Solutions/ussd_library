<?php

declare(strict_types=1);

namespace App\Libraries\Ussd\Security;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Moffhub\Ussd\Services\UssdDatabaseService;

class UssdRateLimiter
{
    protected $config;

    protected $prefix;

    protected ?UssdDatabaseService $databaseService = null;

    public function __construct($config = [])
    {
        $this->config = array_merge([
            'max_requests_per_minute' => 10,
            'max_requests_per_hour' => 100,
            'max_requests_per_day' => 500,
            'blocked_duration' => 300, // 5 minutes
            'whitelist' => [],
            'blacklist' => [],
            'enabled' => true,
            'prefix' => 'ussd_rate_limit_',
        ], $config);

        $this->prefix = $this->config['prefix'];
    }

    public function setDatabaseService(UssdDatabaseService $databaseService): void
    {
        $this->databaseService = $databaseService;
    }

    public function allow($phoneNumber, $action = 'request'): bool
    {
        if (! $this->isEnabled()) {
            return true;
        }

        if ($this->isWhitelisted($phoneNumber)) {
            return true;
        }

        if ($this->isBlacklisted($phoneNumber)) {
            $this->logSecurity('blacklist_blocked', $phoneNumber, $action);

            return false;
        }

        if ($this->isBlocked($phoneNumber)) {
            $this->logSecurity('rate_limit_blocked', $phoneNumber, $action);

            return false;
        }

        if (! $this->checkRateLimit($phoneNumber, $action)) {
            $this->blockUser($phoneNumber);
            $this->logSecurity('rate_limit_exceeded', $phoneNumber, $action);

            return false;
        }

        $this->recordRequest($phoneNumber, $action);

        return true;
    }

    protected function checkRateLimit($phoneNumber, $action): bool
    {
        $windows = [
            'minute' => ['limit' => $this->config['max_requests_per_minute'], 'seconds' => 60],
            'hour' => ['limit' => $this->config['max_requests_per_hour'], 'seconds' => 3600],
            'day' => ['limit' => $this->config['max_requests_per_day'], 'seconds' => 86400],
        ];

        foreach ($windows as $window => $config) {
            $count = $this->getRequestCount($phoneNumber, $action, $config['seconds']);

            if ($count >= $config['limit']) {
                Log::warning("Rate limit exceeded for $window window", [
                    'phone' => $phoneNumber,
                    'action' => $action,
                    'count' => $count,
                    'limit' => $config['limit'],
                ]);

                return false;
            }
        }

        return true;
    }

    protected function recordRequest($phoneNumber, $action): void
    {
        $key = $this->getRequestKey($phoneNumber, $action);
        $now = Carbon::now();

        $requests = Cache::get($key, []);

        $requests[] = $now->timestamp;

        $cutoff = $now->subDay()->timestamp;
        $requests = array_filter($requests, function ($timestamp) use ($cutoff) {
            return $timestamp > $cutoff;
        });

        Cache::put($key, $requests, 86400);

        $this->saveRateLimitToDatabase($phoneNumber, $action, $requests);
    }

    protected function saveRateLimitToDatabase($phoneNumber, $action, $requests): void
    {
        if ($this->databaseService) {
            $this->databaseService->saveRateLimit($phoneNumber, $action, $requests);

            return;
        }

        try {
            DB::table('ussd_rate_limits')->updateOrInsert(
                ['phone_number' => $phoneNumber, 'action' => $action],
                [
                    'request_timestamps' => json_encode($requests),
                    'updated_at' => now(),
                    'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                ]
            );
        } catch (\Exception $e) {
            Log::error('Failed to save rate limit data to database', [
                'error' => $e->getMessage(),
                'phone_number' => $phoneNumber,
                'action' => $action,
            ]);
        }
    }

    protected function getRequestCount($phoneNumber, $action, $seconds): int
    {
        $key = $this->getRequestKey($phoneNumber, $action);
        $requests = Cache::get($key, []);

        $cutoff = Carbon::now()->subSeconds($seconds)->timestamp;

        return count(array_filter($requests, function ($timestamp) use ($cutoff) {
            return $timestamp > $cutoff;
        }));
    }

    protected function blockUser($phoneNumber): void
    {
        $key = $this->getBlockKey($phoneNumber);
        $blockedUntil = Carbon::now()->addSeconds($this->config['blocked_duration']);

        Cache::put($key, $blockedUntil->timestamp, $this->config['blocked_duration']);

        if ($this->databaseService) {
            $this->databaseService->saveRateLimit($phoneNumber, 'request', [], $blockedUntil);
        } else {
            try {
                DB::table('ussd_rate_limits')->updateOrInsert(
                    ['phone_number' => $phoneNumber, 'action' => 'request'],
                    [
                        'blocked_until' => $blockedUntil,
                        'violation_count' => DB::raw('COALESCE(violation_count, 0) + 1'),
                        'last_violation' => now(),
                        'updated_at' => now(),
                        'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                    ]
                );
            } catch (\Exception $e) {
                Log::error('Failed to save rate limit block to database', [
                    'error' => $e->getMessage(),
                    'phone_number' => $phoneNumber,
                ]);
            }
        }

        Log::warning('User temporarily blocked', [
            'phone' => $phoneNumber,
            'blocked_until' => $blockedUntil->toDateTimeString(),
            'duration' => $this->config['blocked_duration'],
        ]);
    }

    protected function isBlocked($phoneNumber): bool
    {
        $key = $this->getBlockKey($phoneNumber);
        $blockedUntil = Cache::get($key);

        if ($blockedUntil && $blockedUntil > Carbon::now()->timestamp) {
            return true;
        }

        if ($blockedUntil) {
            Cache::forget($key);
        }

        return false;
    }

    protected function isWhitelisted($phoneNumber): bool
    {
        return in_array($phoneNumber, $this->config['whitelist']);
    }

    protected function isBlacklisted($phoneNumber): bool
    {
        return in_array($phoneNumber, $this->config['blacklist']);
    }

    protected function getRequestKey($phoneNumber, $action): string
    {
        return "{$this->prefix}requests_{$phoneNumber}_{$action}";
    }

    protected function getBlockKey($phoneNumber): string
    {
        return "{$this->prefix}blocked_$phoneNumber";
    }

    protected function isEnabled()
    {
        return $this->config['enabled'] ?? true;
    }

    protected function logSecurity($event, $phoneNumber, $action, $additional = []): void
    {
        Log::channel('security')->warning("USSD Security Event: $event", array_merge([
            'phone' => $phoneNumber,
            'action' => $action,
            'timestamp' => now()->toDateTimeString(),
            'ip' => request()->ip() ?? 'unknown',
        ], $additional));
    }

    public function getStatus($phoneNumber): array
    {
        return [
            'whitelisted' => $this->isWhitelisted($phoneNumber),
            'blacklisted' => $this->isBlacklisted($phoneNumber),
            'blocked' => $this->isBlocked($phoneNumber),
            'requests_minute' => $this->getRequestCount($phoneNumber, 'request', 60),
            'requests_hour' => $this->getRequestCount($phoneNumber, 'request', 3600),
            'requests_day' => $this->getRequestCount($phoneNumber, 'request', 86400),
            'limits' => [
                'minute' => $this->config['max_requests_per_minute'],
                'hour' => $this->config['max_requests_per_hour'],
                'day' => $this->config['max_requests_per_day'],
            ],
        ];
    }

    public function manualBlock($phoneNumber, $duration = null): void
    {
        $duration = $duration ?: $this->config['blocked_duration'];
        $key = $this->getBlockKey($phoneNumber);
        $blockedUntil = Carbon::now()->addSeconds($duration);

        Cache::put($key, $blockedUntil->timestamp, $duration);

        $this->logSecurity('manual_block', $phoneNumber, 'admin', [
            'duration' => $duration,
            'blocked_until' => $blockedUntil->toDateTimeString(),
        ]);
    }

    public function unblock($phoneNumber): void
    {
        $key = $this->getBlockKey($phoneNumber);
        Cache::forget($key);

        $this->logSecurity('manual_unblock', $phoneNumber, 'admin');
    }
}
