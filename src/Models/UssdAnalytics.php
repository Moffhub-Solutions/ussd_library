<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $event_type
 * @property string|null $phone_number
 * @property string|null $session_id
 * @property array|null $data
 * @property CarbonImmutable|null $timestamp
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class UssdAnalytics extends Model
{
    protected $casts = [
        'timestamp' => 'datetime',
        'data' => 'json',
    ];
}
