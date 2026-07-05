<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Opt-in for host applications: keep the numeric auto-increment `id` for
 * internal linkage (foreign keys, joins) and expose a `ulid` column for
 * anything UI- or URL-facing. Add a `ulid` char(26) unique column to the
 * model's table, then use this trait to fill it on create and bind routes
 * to it.
 */
trait HasUlidRouteKey
{
    public static function bootHasUlidRouteKey(): void
    {
        static::creating(function (Model $model): void {
            if (empty($model->getAttribute('ulid'))) {
                $model->setAttribute('ulid', strtolower((string) Str::ulid()));
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }
}
