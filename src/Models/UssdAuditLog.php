<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Moffhub\Ussd\Enum\SeverityType;

/**
 * @property int $id
 * @property CarbonImmutable|null $timestamp
 * @property string $action
 * @property string $category
 * @property string|null $phone_number
 * @property string|null $session_id
 * @property string|null $ip_address
 * @property array|null $details
 * @property array|null $metadata
 * @property SeverityType $severity
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class UssdAuditLog extends Model
{
    protected $casts = [
        'timestamp' => 'immutable_datetime',
        'details' => 'json',
        'metadata' => 'josn',
        'severity' => SeverityType::class,
    ];
}
