<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $menu_name
 * @property string $option_selected
 * @property int $access_count
 * @property int $completion_count
 * @property int $drop_off_count
 * @property float $avg_time_spent
 * @property array|null $user_segments
 * @property string $date
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class UssdMenuStatistic extends Model {}
