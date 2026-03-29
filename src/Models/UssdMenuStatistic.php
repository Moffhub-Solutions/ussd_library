<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Moffhub\Ussd\Database\Factories\UssdMenuStatisticFactory;

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
class UssdMenuStatistic extends Model
{
    /** @use HasFactory<UssdMenuStatisticFactory> */
    use HasFactory;

    protected static function newFactory(): UssdMenuStatisticFactory
    {
        return UssdMenuStatisticFactory::new();
    }
}
