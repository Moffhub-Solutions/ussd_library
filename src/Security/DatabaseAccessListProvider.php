<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Security;

use DateTimeInterface;
use Illuminate\Support\Facades\Cache;
use Moffhub\Ussd\Interfaces\AccessListProviderInterface;
use Moffhub\Ussd\Models\UssdAccessList;

/**
 * Database-backed access list provider.
 *
 * Uses the UssdAccessList Eloquent model to store whitelist/blacklist entries.
 * Supports caching for performance.
 */
class DatabaseAccessListProvider implements AccessListProviderInterface
{
    protected int $cacheTtl = 300; // 5 minutes

    protected string $cachePrefix = 'ussd_access_';

    protected bool $cacheEnabled = true;

    public function __construct(array $config = [])
    {
        $this->cacheTtl = $config['cache_ttl'] ?? 300;
        $this->cachePrefix = $config['cache_prefix'] ?? 'ussd_access_';
        $this->cacheEnabled = $config['cache_enabled'] ?? true;
    }

    public function isWhitelisted(string $phoneNumber): bool
    {
        if ($this->cacheEnabled) {
            return Cache::remember(
                $this->cachePrefix.'whitelist_'.$phoneNumber,
                $this->cacheTtl,
                fn (): bool => UssdAccessList::isWhitelisted($phoneNumber)
            );
        }

        return UssdAccessList::isWhitelisted($phoneNumber);
    }

    public function isBlacklisted(string $phoneNumber): bool
    {
        if ($this->cacheEnabled) {
            return Cache::remember(
                $this->cachePrefix.'blacklist_'.$phoneNumber,
                $this->cacheTtl,
                fn (): bool => UssdAccessList::isBlacklisted($phoneNumber)
            );
        }

        return UssdAccessList::isBlacklisted($phoneNumber);
    }

    public function addToWhitelist(
        string $phoneNumber,
        ?string $reason = null,
        ?string $addedBy = null,
        ?DateTimeInterface $expiresAt = null,
        array $metadata = []
    ): bool {
        $entry = UssdAccessList::addToWhitelist($phoneNumber, $reason, $addedBy, $expiresAt, $metadata);

        $this->clearCache($phoneNumber);

        return $entry->exists;
    }

    public function addToBlacklist(
        string $phoneNumber,
        ?string $reason = null,
        ?string $addedBy = null,
        ?DateTimeInterface $expiresAt = null,
        array $metadata = []
    ): bool {
        $entry = UssdAccessList::addToBlacklist($phoneNumber, $reason, $addedBy, $expiresAt, $metadata);

        $this->clearCache($phoneNumber);

        return $entry->exists;
    }

    public function removeFromWhitelist(string $phoneNumber): bool
    {
        $result = UssdAccessList::removeFromWhitelist($phoneNumber);

        $this->clearCache($phoneNumber);

        return $result;
    }

    public function removeFromBlacklist(string $phoneNumber): bool
    {
        $result = UssdAccessList::removeFromBlacklist($phoneNumber);

        $this->clearCache($phoneNumber);

        return $result;
    }

    public function getWhitelistedNumbers(): array
    {
        if ($this->cacheEnabled) {
            return Cache::remember(
                $this->cachePrefix.'whitelist_all',
                $this->cacheTtl,
                fn (): array => UssdAccessList::getWhitelistedNumbers()
            );
        }

        return UssdAccessList::getWhitelistedNumbers();
    }

    public function getBlacklistedNumbers(): array
    {
        if ($this->cacheEnabled) {
            return Cache::remember(
                $this->cachePrefix.'blacklist_all',
                $this->cacheTtl,
                fn (): array => UssdAccessList::getBlacklistedNumbers()
            );
        }

        return UssdAccessList::getBlacklistedNumbers();
    }

    /**
     * Clear cache for a specific phone number.
     */
    protected function clearCache(string $phoneNumber): void
    {
        if (! $this->cacheEnabled) {
            return;
        }

        Cache::forget($this->cachePrefix.'whitelist_'.$phoneNumber);
        Cache::forget($this->cachePrefix.'blacklist_'.$phoneNumber);
        Cache::forget($this->cachePrefix.'whitelist_all');
        Cache::forget($this->cachePrefix.'blacklist_all');
    }

    /**
     * Clear all access list caches.
     */
    public function clearAllCache(): void
    {
        // Note: This requires cache tags or manual tracking for full implementation
        // For now, the individual caches will expire naturally
    }
}
