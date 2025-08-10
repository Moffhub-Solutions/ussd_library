<?php
declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $phone_number
 * @property string $session_id
 * @property string $session_data
 * @property string|null $current_menu
 * @property string|null $status
 * @property int $step
 * @property int $access_count
 * @property CarbonImmutable|null $last_access_at
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class UssdSession extends Model
{

}
