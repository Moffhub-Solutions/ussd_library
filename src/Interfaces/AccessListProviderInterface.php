<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Interfaces;

/**
 * Interface for whitelist/blacklist providers.
 *
 * Implement this interface to provide custom storage for access lists.
 * The default implementation uses the UssdAccessList Eloquent model.
 */
interface AccessListProviderInterface
{
    /**
     * Check if a phone number is whitelisted.
     */
    public function isWhitelisted(string $phoneNumber): bool;

    /**
     * Check if a phone number is blacklisted.
     */
    public function isBlacklisted(string $phoneNumber): bool;

    /**
     * Add a phone number to the whitelist.
     *
     * @param array<string, mixed> $metadata Additional metadata
     */
    public function addToWhitelist(
        string $phoneNumber,
        ?string $reason = null,
        ?string $addedBy = null,
        ?\DateTimeInterface $expiresAt = null,
        array $metadata = []
    ): bool;

    /**
     * Add a phone number to the blacklist.
     *
     * @param array<string, mixed> $metadata Additional metadata
     */
    public function addToBlacklist(
        string $phoneNumber,
        ?string $reason = null,
        ?string $addedBy = null,
        ?\DateTimeInterface $expiresAt = null,
        array $metadata = []
    ): bool;

    /**
     * Remove a phone number from the whitelist.
     */
    public function removeFromWhitelist(string $phoneNumber): bool;

    /**
     * Remove a phone number from the blacklist.
     */
    public function removeFromBlacklist(string $phoneNumber): bool;

    /**
     * Get all whitelisted phone numbers.
     *
     * @return array<int, string>
     */
    public function getWhitelistedNumbers(): array;

    /**
     * Get all blacklisted phone numbers.
     *
     * @return array<int, string>
     */
    public function getBlacklistedNumbers(): array;
}
