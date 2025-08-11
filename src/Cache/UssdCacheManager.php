<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Cache;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class UssdCacheManager
{
    protected array $config;

    protected string $prefix;

    protected int $defaultTtl;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'prefix' => 'ussd_cache_',
            'default_ttl' => 300, // 5 minutes
            'menu_content_ttl' => 3600, // 1 hour
            'data_provider_ttl' => 600, // 10 minutes
            'user_data_ttl' => 1800, // 30 minutes
            'enabled' => true,
        ], $config);

        $this->prefix = $this->config['prefix'];
        $this->defaultTtl = $this->config['default_ttl'];
    }

    public function cacheMenuContent(string $menuName, mixed $content, ?string $userId = null, ?int $ttl = null): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $key = $this->getMenuContentKey($menuName, $userId);
        $ttl = $ttl ?: $this->config['menu_content_ttl'];

        return Cache::put($key, [
            'content' => $content,
            'created_at' => now(),
            'menu_name' => $menuName,
            'user_id' => $userId,
        ], $ttl);
    }

    public function getMenuContent(string $menuName, ?string $userId = null): mixed
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $key = $this->getMenuContentKey($menuName, $userId);
        $cached = Cache::get($key);

        if ($cached && $this->isValidCache($cached)) {
            Log::info("Cache hit for menu: {$menuName}", ['user_id' => $userId]);

            return $cached['content'];
        }

        Log::info("Cache miss for menu: {$menuName}", ['user_id' => $userId]);

        return null;
    }

    public function cacheDataProvider(string $providerKey, array $filters, mixed $data, ?int $ttl = null): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $key = $this->getDataProviderKey($providerKey, $filters);
        $ttl = $ttl ?: $this->config['data_provider_ttl'];

        $tags = ['data_provider', "provider_{$providerKey}"];

        return Cache::tags($tags)->put($key, [
            'data' => $data,
            'created_at' => now(),
            'provider_key' => $providerKey,
            'filters' => $filters,
            'count' => is_array($data) ? count($data) : 1,
        ], $ttl);
    }

    public function getDataProvider(string $providerKey, array $filters = []): mixed
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $key = $this->getDataProviderKey($providerKey, $filters);
        $cached = Cache::get($key);

        if ($cached && $this->isValidCache($cached)) {
            Log::info('Data provider cache hit', [
                'provider' => $providerKey,
                'filters' => $filters,
                'count' => $cached['count'],
            ]);

            return $cached['data'];
        }

        return null;
    }

    public function cacheUserData(string $userId, string $key, mixed $data, ?int $ttl = null): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $cacheKey = $this->getUserDataKey($userId, $key);
        $ttl = $ttl ?: $this->config['user_data_ttl'];

        return Cache::put($cacheKey, [
            'data' => $data,
            'created_at' => now(),
            'user_id' => $userId,
            'key' => $key,
        ], $ttl);
    }

    public function getUserData(string $userId, string $key): mixed
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $cacheKey = $this->getUserDataKey($userId, $key);
        $cached = Cache::get($cacheKey);

        if ($cached && $this->isValidCache($cached)) {
            return $cached['data'];
        }

        return null;
    }

    public function invalidate(string $type, ?string $identifier = null): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        switch ($type) {
            case 'menu':
                if ($identifier) {
                    return $this->invalidateMenuCache($identifier);
                }
                break;

            case 'data_provider':
                if ($identifier) {
                    return Cache::tags("provider_{$identifier}")->flush();
                }

                return Cache::tags('data_provider')->flush();

            case 'user':
                if ($identifier) {
                    return $this->invalidateUserCache($identifier);
                }
                break;

            case 'all':
                return $this->invalidateAll();
        }

        return false;
    }

    public function warmUp(array $strategies = []): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        Log::info('Starting cache warm-up', ['strategies' => $strategies]);

        foreach ($strategies as $strategy => $config) {
            try {
                switch ($strategy) {
                    case 'menus':
                        $this->warmUpMenus($config);
                        break;

                    case 'data':
                        $this->warmUpDataProviders($config);
                        break;
                }
            } catch (\Exception $e) {
                Log::error("Cache warm-up failed for strategy: {$strategy}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Cache warm-up completed');

        return true;
    }

    public function getStats(): array
    {
        if (! $this->isEnabled()) {
            return ['enabled' => false];
        }

        return [
            'enabled' => true,
            'prefix' => $this->prefix,
            'default_ttl' => $this->defaultTtl,
            'config' => $this->config,
            'memory_usage' => $this->getMemoryUsage(),
            'cache_keys_count' => $this->getCacheKeysCount(),
        ];
    }

    protected function getMenuContentKey(string $menuName, ?string $userId = null): string
    {
        $base = "{$this->prefix}menu_$menuName";

        return $userId ? "{$base}_user_$userId" : $base;
    }

    protected function getDataProviderKey(string $providerKey, array $filters): string
    {
        $filterHash = md5(serialize($filters));

        return "{$this->prefix}data_{$providerKey}_{$filterHash}";
    }

    protected function getUserDataKey(string $userId, string $key): string
    {
        return "{$this->prefix}user_{$userId}_{$key}";
    }

    protected function isValidCache(mixed $cached): bool
    {
        return isset($cached['created_at']) &&
               $cached['created_at']->isAfter(now()->subSeconds($this->defaultTtl));
    }

    protected function isEnabled(): bool
    {
        return $this->config['enabled'] ?? true;
    }

    protected function invalidateMenuCache(string $menuName): bool
    {
        $pattern = "{$this->prefix}menu_$menuName*";

        return Cache::flush(); // In production, use more specific invalidation
    }

    protected function invalidateUserCache(string $userId): bool
    {
        $pattern = "{$this->prefix}user_$userId*";

        return Cache::flush(); // In production, use more specific invalidation
    }

    protected function invalidateAll(): bool
    {
        return Cache::flush();
    }

    protected function warmUpMenus(array $config): void
    {
        // Implementation depends on your menu structure
        Log::info('Warming up menu cache', $config);
    }

    /**
     * Warm up data provider cache
     */
    protected function warmUpDataProviders(array $config): void
    {
        // Implementation depends on your data providers
        Log::info('Warming up data provider cache', $config);
    }

    protected function getMemoryUsage(): int
    {
        return memory_get_usage(true);
    }

    protected function getCacheKeysCount(): int
    {
        // This is a simplified implementation
        // In production, you'd want to implement this based on your cache driver
        return 0;
    }
}
