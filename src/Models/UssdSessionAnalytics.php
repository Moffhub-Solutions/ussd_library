<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string|null $phone_number
 * @property string|null $session_id
 * @property string $event_type
 * @property string|null $menu_name
 * @property string|null $action
 * @property array|null $metadata
 * @property \Carbon\CarbonImmutable|null $event_timestamp
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 */
class UssdSessionAnalytics extends Model {}
