<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $session_id
 * @property string $phone_number
 * @property string|null $current_menu
 * @property array|null $session_data
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $last_activity
 * @property CarbonImmutable|null $ended_at
 * @property int $total_interactions
 * @property array|null $user_journey
 * @property bool $completed
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class UssdUserSession extends Model {}
