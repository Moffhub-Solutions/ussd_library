<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Events;

use Illuminate\Foundation\Events\Dispatchable;

class NavigationPerformed
{
    use Dispatchable;

    public function __construct(
        public readonly string $sessionId,
        public readonly string $action,
    ) {}
}
