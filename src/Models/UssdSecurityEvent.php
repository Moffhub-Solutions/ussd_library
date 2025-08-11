<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Moffhub\Ussd\Enum\SeverityType;

/**
 * @property int $id
 * @property string $event_type
 * @property string|null $phone_number
 * @property string|null $session_id
 * @property string|null $ip_address
 * @property SeverityType $severity
 * @property array|null $event_data
 * @property string $status
 * @property CarbonImmutable|null $detected_at
 * @property CarbonImmutable|null $resolved_at
 * @property string|null $resolution_notes
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class UssdSecurityEvent extends Model {}
