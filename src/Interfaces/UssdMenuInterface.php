<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Interfaces;

use Moffhub\Ussd\UssdFramework;
use Moffhub\Ussd\UssdResponse;
use Moffhub\Ussd\UssdSession;

interface UssdMenuInterface
{
    /**
     * Process user input and return appropriate response
     *
     * @param  string  $input  The user input
     * @param  UssdSession  $session  The current session
     * @return UssdResponse The response
     */
    public function process(string $input, UssdSession $session): UssdResponse;

    /**
     * Set the framework instance for this menu
     *
     * @param  UssdFramework  $framework  The framework instance
     */
    public function setFramework(UssdFramework $framework): void;

    public function display(UssdSession $session): UssdResponse;
}
