<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $metric_name
 * @property string $metric_category
 * @property string $metric_value
 * @property string|null $phone_number
 * @property string|null $session_id
 * @property array|null $dimensions
 * @property array|null $metadata
 * @property CarbonImmutable $recorded_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class UssdBusinessMetric extends Model {}
