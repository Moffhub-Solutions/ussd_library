<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Interfaces;

/**
 * Backing store for live USSD session state, keyed by a session cache key.
 *
 * The default (CacheSessionStore) uses Laravel's cache. Bind your own
 * implementation to this interface in the container to change where/how live
 * session state is held (e.g. a dedicated Redis connection) without touching
 * the framework.
 */
interface SessionStoreInterface
{
    /**
     * Fetch stored session data, or null if absent.
     *
     * @return array<string, mixed>|null
     */
    public function get(string $key): ?array;

    /**
     * Persist session data for the given TTL (seconds).
     *
     * @param  array<string, mixed>  $value
     */
    public function put(string $key, array $value, int $ttlSeconds): void;

    public function has(string $key): bool;

    public function forget(string $key): void;
}
