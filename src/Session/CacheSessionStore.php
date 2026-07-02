<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Session;

use Illuminate\Support\Facades\Cache;
use Moffhub\Ussd\Interfaces\SessionStoreInterface;

/**
 * Default session store: Laravel's cache. Behaviour matches the framework's
 * historical direct Cache usage exactly.
 */
class CacheSessionStore implements SessionStoreInterface
{
    public function get(string $key): ?array
    {
        $value = Cache::get($key);

        return is_array($value) ? $value : null;
    }

    public function put(string $key, array $value, int $ttlSeconds): void
    {
        Cache::put($key, $value, $ttlSeconds);
    }

    public function has(string $key): bool
    {
        return Cache::has($key);
    }

    public function forget(string $key): void
    {
        Cache::forget($key);
    }
}
