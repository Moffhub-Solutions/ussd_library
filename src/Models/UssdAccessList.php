<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * USSD Access List Model.
 *
 * Manages whitelist and blacklist entries for rate limiting.
 * Users can build a UI around this model to manage access control.
 *
 * @property int $id
 * @property string $phone_number
 * @property string $type whitelist|blacklist
 * @property string|null $reason
 * @property string|null $added_by
 * @property Carbon|null $expires_at
 * @property bool $is_active
 * @property array|null $metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 * @method static \Illuminate\Database\Eloquent\Builder<static> where(string|\Closure $column, mixed $operator = null, mixed $value = null, string $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder<static> whitelist()
 * @method static \Illuminate\Database\Eloquent\Builder<static> blacklist()
 * @method static \Illuminate\Database\Eloquent\Builder<static> active()
 * @method static \Illuminate\Database\Eloquent\Builder<static> forPhone(string $phoneNumber)
 * @method static static updateOrCreate(array $attributes, array $values = [])
 */
class UssdAccessList extends Model
{
    protected $table = 'ussd_access_lists';

    protected $fillable = [
        'phone_number',
        'type',
        'reason',
        'added_by',
        'expires_at',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'expires_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Scope to get whitelist entries.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeWhitelist(Builder $query): Builder
    {
        return $query->where('type', 'whitelist');
    }

    /**
     * Scope to get blacklist entries.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeBlacklist(Builder $query): Builder
    {
        return $query->where('type', 'blacklist');
    }

    /**
     * Scope to get only active entries.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(function ($q): void {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Scope to find by phone number.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeForPhone(Builder $query, string $phoneNumber): Builder
    {
        return $query->where('phone_number', $phoneNumber);
    }

    /**
     * Check if this entry has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Check if this entry is currently valid (active and not expired).
     */
    public function isValid(): bool
    {
        return $this->is_active && ! $this->isExpired();
    }

    /**
     * Add a phone number to the whitelist.
     */
    public static function addToWhitelist(
        string $phoneNumber,
        ?string $reason = null,
        ?string $addedBy = null,
        ?\DateTimeInterface $expiresAt = null,
        ?array $metadata = null
    ): static {
        return static::updateOrCreate(
            ['phone_number' => $phoneNumber, 'type' => 'whitelist'],
            [
                'reason' => $reason,
                'added_by' => $addedBy,
                'expires_at' => $expiresAt,
                'is_active' => true,
                'metadata' => $metadata,
            ]
        );
    }

    /**
     * Add a phone number to the blacklist.
     */
    public static function addToBlacklist(
        string $phoneNumber,
        ?string $reason = null,
        ?string $addedBy = null,
        ?\DateTimeInterface $expiresAt = null,
        ?array $metadata = null
    ): static {
        return static::updateOrCreate(
            ['phone_number' => $phoneNumber, 'type' => 'blacklist'],
            [
                'reason' => $reason,
                'added_by' => $addedBy,
                'expires_at' => $expiresAt,
                'is_active' => true,
                'metadata' => $metadata,
            ]
        );
    }

    /**
     * Remove a phone number from the whitelist.
     */
    public static function removeFromWhitelist(string $phoneNumber): bool
    {
        return static::where('phone_number', $phoneNumber)
            ->where('type', 'whitelist')
            ->update(['is_active' => false]) > 0;
    }

    /**
     * Remove a phone number from the blacklist.
     */
    public static function removeFromBlacklist(string $phoneNumber): bool
    {
        return static::where('phone_number', $phoneNumber)
            ->where('type', 'blacklist')
            ->update(['is_active' => false]) > 0;
    }

    /**
     * Check if a phone number is whitelisted.
     */
    public static function isWhitelisted(string $phoneNumber): bool
    {
        return static::where('type', 'whitelist')
            ->where('is_active', true)
            ->where(function ($q): void {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->where('phone_number', $phoneNumber)
            ->exists();
    }

    /**
     * Check if a phone number is blacklisted.
     */
    public static function isBlacklisted(string $phoneNumber): bool
    {
        return static::where('type', 'blacklist')
            ->where('is_active', true)
            ->where(function ($q): void {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->where('phone_number', $phoneNumber)
            ->exists();
    }

    /**
     * Get all active whitelisted phone numbers.
     *
     * @return array<int, string>
     */
    public static function getWhitelistedNumbers(): array
    {
        return static::where('type', 'whitelist')
            ->where('is_active', true)
            ->where(function ($q): void {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->pluck('phone_number')
            ->toArray();
    }

    /**
     * Get all active blacklisted phone numbers.
     *
     * @return array<int, string>
     */
    public static function getBlacklistedNumbers(): array
    {
        return static::where('type', 'blacklist')
            ->where('is_active', true)
            ->where(function ($q): void {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->pluck('phone_number')
            ->toArray();
    }
}
