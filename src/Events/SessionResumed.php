<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Events;

use Illuminate\Foundation\Events\Dispatchable;

class SessionResumed
{
    use Dispatchable;

    public function __construct(
        public readonly string $sessionId,
        public readonly string $phone,
        public readonly bool $wasRecovered,
    ) {}
}
