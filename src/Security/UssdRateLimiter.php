<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Security;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Moffhub\Ussd\Interfaces\AccessListProviderInterface;
use Moffhub\Ussd\Services\UssdDatabaseService;

/**
 * USSD Rate Limiter.
 *
 * Provides rate limiting with support for:
 * - Configurable request limits (per minute/hour/day)
 * - Static whitelist/blacklist from config
 * - Database-backed whitelist/blacklist via AccessListProviderInterface
 * - Automatic blocking after violations
 * - Manual block/unblock operations
 */
class UssdRateLimiter
{
    protected array $config = [];

    protected string $prefix;

    protected ?UssdDatabaseService $databaseService = null;

    protected ?AccessListProviderInterface $accessListProvider = null;

    public function __construct(array $config = [])
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
            'use_database_lists' => false,
        ], $config);

        $this->prefix = $this->config['prefix'];

        // Auto-initialize database provider if enabled
        if ($this->config['use_database_lists'] ?? false) {
            $this->accessListProvider = new DatabaseAccessListProvider($this->config);
        }
    }

    /**
     * Set the database service for persistence.
     */
    public function setDatabaseService(UssdDatabaseService $databaseService): void
    {
        $this->databaseService = $databaseService;
    }

    /**
     * Set a custom access list provider.
     *
     * This allows users to provide their own whitelist/blacklist implementation.
     */
    public function setAccessListProvider(AccessListProviderInterface $provider): void
    {
        $this->accessListProvider = $provider;
    }

    /**
     * Get the current access list provider.
     */
    public function getAccessListProvider(): ?AccessListProviderInterface
    {
        return $this->accessListProvider;
    }

    /**
     * Add a phone number to the whitelist.
     */
    public function addToWhitelist(
        string $phoneNumber,
        ?string $reason = null,
        ?string $addedBy = null,
        ?DateTimeInterface $expiresAt = null
    ): bool {
        if ($this->accessListProvider) {
            return $this->accessListProvider->addToWhitelist($phoneNumber, $reason, $addedBy, $expiresAt);
        }

        // Fallback to config array (in-memory only)
        if (! in_array($phoneNumber, $this->config['whitelist'])) {
            $this->config['whitelist'][] = $phoneNumber;
        }

        return true;
    }

    /**
     * Add a phone number to the blacklist.
     */
    public function addToBlacklist(
        string $phoneNumber,
        ?string $reason = null,
        ?string $addedBy = null,
        ?DateTimeInterface $expiresAt = null
    ): bool {
        if ($this->accessListProvider) {
            return $this->accessListProvider->addToBlacklist($phoneNumber, $reason, $addedBy, $expiresAt);
        }

        // Fallback to config array (in-memory only)
        if (! in_array($phoneNumber, $this->config['blacklist'])) {
            $this->config['blacklist'][] = $phoneNumber;
        }

        return true;
    }

    /**
     * Remove a phone number from the whitelist.
     */
    public function removeFromWhitelist(string $phoneNumber): bool
    {
        if ($this->accessListProvider) {
            return $this->accessListProvider->removeFromWhitelist($phoneNumber);
        }

        $this->config['whitelist'] = array_values(
            array_diff($this->config['whitelist'], [$phoneNumber])
        );

        return true;
    }

    /**
     * Remove a phone number from the blacklist.
     */
    public function removeFromBlacklist(string $phoneNumber): bool
    {
        if ($this->accessListProvider) {
            return $this->accessListProvider->removeFromBlacklist($phoneNumber);
        }

        $this->config['blacklist'] = array_values(
            array_diff($this->config['blacklist'], [$phoneNumber])
        );

        return true;
    }

    public function allow(string $phoneNumber, string $action = 'request'): bool
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

    protected function checkRateLimit(string $phoneNumber, string $action): bool
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

    protected function recordRequest(string $phoneNumber, string $action): void
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

    protected function saveRateLimitToDatabase(string $phoneNumber, string $action, array $requests): void
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

    protected function getRequestCount(string $phoneNumber, string $action, int|float $seconds): int
    {
        $key = $this->getRequestKey($phoneNumber, $action);
        $requests = Cache::get($key, []);

        $cutoff = Carbon::now()->subSeconds($seconds)->timestamp;

        return count(array_filter($requests, function ($timestamp) use ($cutoff) {
            return $timestamp > $cutoff;
        }));
    }

    protected function blockUser(string $phoneNumber): void
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

    protected function isBlocked(string $phoneNumber): bool
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

    protected function isWhitelisted(string $phoneNumber): bool
    {
        // Check config array first (for static entries)
        if (in_array($phoneNumber, $this->config['whitelist'])) {
            return true;
        }

        // Check database provider if available
        if ($this->accessListProvider) {
            return $this->accessListProvider->isWhitelisted($phoneNumber);
        }

        return false;
    }

    protected function isBlacklisted(string $phoneNumber): bool
    {
        // Check config array first (for static entries)
        if (in_array($phoneNumber, $this->config['blacklist'])) {
            return true;
        }

        // Check database provider if available
        if ($this->accessListProvider) {
            return $this->accessListProvider->isBlacklisted($phoneNumber);
        }

        return false;
    }

    protected function getRequestKey(string $phoneNumber, string $action): string
    {
        return "{$this->prefix}requests_{$phoneNumber}_{$action}";
    }

    protected function getBlockKey(string $phoneNumber): string
    {
        return "{$this->prefix}blocked_$phoneNumber";
    }

    protected function isEnabled(): bool
    {
        return $this->config['enabled'] ?? true;
    }

    protected function logSecurity(string $event, string $phoneNumber, string $action, array $additional = []): void
    {
        Log::channel('security')->warning("USSD Security Event: $event", array_merge([
            'phone' => $phoneNumber,
            'action' => $action,
            'timestamp' => now()->toDateTimeString(),
            'ip' => request()->ip() ?? 'unknown',
        ], $additional));
    }

    public function getStatus(string $phoneNumber): array
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

    public function manualBlock(string $phoneNumber, int|string $duration = 0): void
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

    public function unblock(string $phoneNumber): void
    {
        $key = $this->getBlockKey($phoneNumber);
        Cache::forget($key);

        $this->logSecurity('manual_unblock', $phoneNumber, 'admin');
    }
}
