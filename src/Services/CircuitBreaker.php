<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CircuitBreaker
{
    public const string STATE_CLOSED = 'closed';

    public const string STATE_OPEN = 'open';

    public const string STATE_HALF_OPEN = 'half_open';

    protected int $threshold;

    protected int $cooldownSeconds;

    protected string $cachePrefix = 'ussd_circuit_breaker_';

    public function __construct(?int $threshold = null, ?int $cooldownSeconds = null)
    {
        $this->threshold = $threshold ?? (int) config('ussd.circuit_breaker.threshold', 5);
        $this->cooldownSeconds = $cooldownSeconds ?? (int) config('ussd.circuit_breaker.cooldown_seconds', 60);
    }

    /**
     * Check if a request is allowed for the given endpoint.
     */
    public function isAvailable(string $endpoint): bool
    {
        $state = $this->getState($endpoint);

        if ($state === self::STATE_CLOSED) {
            return true;
        }

        if ($state === self::STATE_OPEN) {
            if ($this->cooldownExpired($endpoint)) {
                $this->transitionTo($endpoint, self::STATE_HALF_OPEN);

                return true;
            }

            return false;
        }

        // Half-open: allow one test request
        return true;
    }

    /**
     * Record a successful call to an endpoint.
     */
    public function recordSuccess(string $endpoint): void
    {
        $state = $this->getState($endpoint);

        if ($state === self::STATE_HALF_OPEN) {
            $this->reset($endpoint);

            Log::info('CircuitBreaker: Circuit closed after successful test request', [
                'endpoint' => $endpoint,
            ]);
        }

        // In closed state, reset failure count on success
        if ($state === self::STATE_CLOSED) {
            Cache::put($this->failureCountKey($endpoint), 0, max(60, $this->cooldownSeconds * 2));
        }
    }

    /**
     * Record a failed call to an endpoint.
     */
    public function recordFailure(string $endpoint): void
    {
        $state = $this->getState($endpoint);

        if ($state === self::STATE_HALF_OPEN) {
            $this->transitionTo($endpoint, self::STATE_OPEN);

            Log::warning('CircuitBreaker: Circuit re-opened after failed test request', [
                'endpoint' => $endpoint,
            ]);

            return;
        }

        $failures = $this->getFailureCount($endpoint) + 1;
        Cache::put($this->failureCountKey($endpoint), $failures, max(60, $this->cooldownSeconds * 2));

        if ($failures >= $this->threshold) {
            $this->transitionTo($endpoint, self::STATE_OPEN);

            Log::warning('CircuitBreaker: Circuit opened due to excessive failures', [
                'endpoint' => $endpoint,
                'failures' => $failures,
                'threshold' => $this->threshold,
            ]);
        }
    }

    /**
     * Get the current state for an endpoint.
     */
    public function getState(string $endpoint): string
    {
        return Cache::get($this->stateKey($endpoint), self::STATE_CLOSED);
    }

    /**
     * Get the failure count for an endpoint.
     */
    public function getFailureCount(string $endpoint): int
    {
        return (int) Cache::get($this->failureCountKey($endpoint), 0);
    }

    /**
     * Reset the circuit breaker for an endpoint.
     */
    public function reset(string $endpoint): void
    {
        Cache::forget($this->stateKey($endpoint));
        Cache::forget($this->failureCountKey($endpoint));
        Cache::forget($this->openedAtKey($endpoint));
    }

    /**
     * Transition to a new state.
     */
    protected function transitionTo(string $endpoint, string $newState): void
    {
        $oldState = $this->getState($endpoint);

        $cacheTtl = max(60, $this->cooldownSeconds * 3);
        Cache::put($this->stateKey($endpoint), $newState, $cacheTtl);

        if ($newState === self::STATE_OPEN) {
            Cache::put($this->openedAtKey($endpoint), time(), $cacheTtl);
        }

        Log::info('CircuitBreaker: State transition', [
            'endpoint' => $endpoint,
            'from' => $oldState,
            'to' => $newState,
        ]);
    }

    /**
     * Check if the cooldown period has expired.
     */
    protected function cooldownExpired(string $endpoint): bool
    {
        $openedAt = Cache::get($this->openedAtKey($endpoint));

        if ($openedAt === null) {
            return true;
        }

        return (time() - (int) $openedAt) >= $this->cooldownSeconds;
    }

    protected function stateKey(string $endpoint): string
    {
        return $this->cachePrefix.'state_'.md5($endpoint);
    }

    protected function failureCountKey(string $endpoint): string
    {
        return $this->cachePrefix.'failures_'.md5($endpoint);
    }

    protected function openedAtKey(string $endpoint): string
    {
        return $this->cachePrefix.'opened_at_'.md5($endpoint);
    }
}
