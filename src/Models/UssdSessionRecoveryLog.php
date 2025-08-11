<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string|null $phone_number
 * @property string|null $old_session_id
 * @property string|null $new_session_id
 * @property CarbonImmutable|null $attempted_at
 * @property string $recovery_type
 * @property array|null $recovery_context
 * @property bool $recovery_successful
 * @property string|null $recovery_notes
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class UssdSessionRecoveryLog extends Model {}
