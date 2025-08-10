<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Actions;

use Closure;
use Moffhub\Ussd\Interfaces\ActionInterface;
use Moffhub\Ussd\UssdFramework;
use Moffhub\Ussd\UssdResponse;
use Moffhub\Ussd\UssdSession;

class CallbackAction implements ActionInterface
{
    protected ?Closure $callback;

    public function __construct(?Closure $callback)
    {
        $this->callback = $callback;
    }

    public function execute(?string $input, UssdSession $session, UssdFramework $framework): UssdResponse
    {
        if (is_callable($this->callback)) {
            $result = call_user_func($this->callback, $input, $session, $framework);
            // Ensure we always return a UssdResponse
            if ($result instanceof UssdResponse) {
                return $result;
            }
            // If callback returns a string, wrap it in a response
            if (is_string($result)) {
                return UssdResponse::end($result);
            }
        }

        return UssdResponse::end('Action completed.');
    }
}
