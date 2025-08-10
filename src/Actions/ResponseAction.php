<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Actions;

use Moffhub\Ussd\Interfaces\ActionInterface;
use Moffhub\Ussd\UssdFramework;
use Moffhub\Ussd\UssdResponse;
use Moffhub\Ussd\UssdSession;

class ResponseAction implements ActionInterface
{
    protected string $message;

    protected string $type;

    /**
     * @param  string  $message  The response message
     * @param  string  $type  The response type (CON or END)
     */
    public function __construct(string $message, string $type = UssdResponse::END)
    {
        $this->message = $message;
        $this->type = $type;
    }

    public function execute(string|null $input, UssdSession $session, UssdFramework $framework): UssdResponse
    {
        return new UssdResponse($this->message, $this->type);
    }
}
