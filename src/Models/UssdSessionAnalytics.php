<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string|null $phone_number
 * @property string|null $session_id
 * @property string $event_type
 * @property string|null $menu_name
 * @property string|null $action
 * @property array|null $metadata
 * @property CarbonImmutable|null $event_timestamp
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class UssdSessionAnalytics extends Model {}
