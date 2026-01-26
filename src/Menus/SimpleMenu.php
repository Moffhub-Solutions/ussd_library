<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Menus;

use Moffhub\Ussd\Interfaces\ActionInterface;
use Moffhub\Ussd\UssdResponse;
use Moffhub\Ussd\UssdSession;

class SimpleMenu extends UssdMenu
{
    protected string $title;

    public function __construct(string $title, protected array $options = [], protected array $actions = [], protected ?string $footer = null)
    {
        parent::__construct($title);
        $this->title = $title;
    }

    #[\Override]
    protected function showInitial(UssdSession $session): UssdResponse
    {
        $message = $this->title."\n";

        foreach ($this->options as $key => $option) {
            $message .= $key.'. '.$option."\n";
        }

        if ($this->footer) {
            $message .= "\n".$this->footer;
        }
        $message = $this->addGlobalNavigation($message, $session);

        return UssdResponse::continue($message);
    }

    protected function processStep(string $input, int $step, UssdSession $session): UssdResponse
    {
        if ($step === 0) {
            $input = trim($input);

            if (! isset($this->options[$input])) {
                return UssdResponse::continue("Invalid option. Please try again.\n".$this->showInitial($session)->getMessage());
            }

            if (isset($this->actions[$input]) && $this->framework) {
                $action = $this->actions[$input];
                if ($action instanceof ActionInterface) {
                    return $action->execute($input, $session, $this->framework);
                }

                if (is_callable($action)) {
                    // Use same signature as UssdMenu::processSimpleMenu: ($input, $session, $framework)
                    return $action($input, $session, $this->framework);
                }
            }

            return UssdResponse::end('Thank you for using our service!');
        }

        return UssdResponse::end('Session ended.');
    }

    #[\Override]
    public function addOption(string $key, string $value, ?callable $action = null): static
    {
        $this->options[$key] = $value;
        if ($action) {
            $this->actions[$key] = $action;
        }

        return $this;
    }

    public function setFooter(string $footer): static
    {
        $this->footer = $footer;

        return $this;
    }
}
