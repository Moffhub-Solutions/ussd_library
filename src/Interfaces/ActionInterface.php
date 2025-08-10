<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Interfaces;

use Moffhub\Ussd\UssdFramework;
use Moffhub\Ussd\UssdResponse;
use Moffhub\Ussd\UssdSession;

interface ActionInterface
{
    /**
     * Execute the action with the given input, session and framework
     *
     * @param  string|null  $input  The user input
     * @param  UssdSession  $session  The current session
     * @param  UssdFramework  $framework  The framework instance
     * @return UssdResponse The response
     */
    public function execute(?string $input, UssdSession $session, UssdFramework $framework): UssdResponse;
}
