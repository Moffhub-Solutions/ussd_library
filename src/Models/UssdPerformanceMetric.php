<?php
declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $action
 * @property string|null $menu_name
 * @property int $duration_ms
 * @property int $memory_usage
 * @property string $phone_number
 * @property string|null $session_id
 * @property array|null $metadata
 * @property CarbonImmutable|null $timestamp
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class UssdPerformanceMetric extends Model
{

}
