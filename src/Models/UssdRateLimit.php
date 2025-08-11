<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $phone_number
 * @property string $action
 * @property array|null $request_timestamps
 * @property CarbonImmutable|null $blocked_until
 * @property int $violation_count
 * @property CarbonImmutable|null $last_violation
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class UssdRateLimit extends Model {}
