<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Actions;

use Closure;
use Illuminate\Support\Facades\Log;
use Moffhub\Ussd\Interfaces\ActionInterface;
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
     * @param  Closure  $callback  The callback function to save data
     * @param  string|null  $successMessage  Message to show on success
     * @param  string|null  $errorMessage  Message to show on error
     */
    public function __construct(Closure $callback, ?string $successMessage = 'Data saved successfully!', ?string $errorMessage = 'Failed to save data.')
    {
        $this->callback = $callback;
        $this->successMessage = $successMessage ?: 'Data saved successfully!';
        $this->errorMessage = $errorMessage ?: 'Failed to save data.';
    }

    public function execute(?string $input, UssdSession $session, UssdFramework $framework): UssdResponse
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

        } catch (\Throwable $e) {
            // Do not let a genuine save failure (DB error, callback bug, arg-order
            // mismatch) masquerade as a benign "couldn't save". Log it, and in
            // debug rethrow so it surfaces instead of being swallowed.
            Log::error('USSD: SaveDataAction callback failed', [
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            if ($framework->getConfig('debug', false)) {
                throw $e;
            }

            return UssdResponse::end($this->errorMessage);
        }
    }
}
