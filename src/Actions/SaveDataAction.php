<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Actions;


use Exception;
use Moffhub\Ussd\UssdFramework;
use Moffhub\Ussd\UssdResponse;
use Moffhub\Ussd\UssdSession;

class SaveDataAction implements ActionInterface
{
    /** @var ?callable */
    protected $callback;
    protected string $successMessage;
    protected string $errorMessage;

    /**
     * @param callable $callback The callback function to save data
     * @param string $successMessage Message to show on success
     * @param string $errorMessage Message to show on error
     */
    public function __construct(callable $callback, string $successMessage = 'Data saved successfully!', string $errorMessage = 'Failed to save data.')
    {
        $this->callback = $callback;
        $this->successMessage = $successMessage;
        $this->errorMessage = $errorMessage;
    }

    public function execute(string $input, UssdSession $session, UssdFramework $framework): UssdResponse
    {
        try {
            $formData = $session->getFormData();

            if (is_callable($this->callback)) {
                $result = call_user_func($this->callback, $formData, $session, $framework);

                if ($result) {
                    return UssdResponse::end($this->successMessage);
                }
            }

            return UssdResponse::end($this->errorMessage);

        } catch (Exception $e) {
            return UssdResponse::end($this->errorMessage);
        }
    }
}
