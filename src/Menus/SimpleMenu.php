<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Menus;

use Moffhub\Ussd\Interfaces\ActionInterface;
use Moffhub\Ussd\UssdResponse;
use Moffhub\Ussd\UssdSession;

class SimpleMenu extends UssdMenu
{
    protected string $title;
    protected array $options = [];
    protected array $actions = [];
    protected string|null $footer;

    public function __construct(string $title, array $options = [], array $actions = [], string|null $footer = null)
    {
        parent::__construct($title);
        $this->title = $title;
        $this->options = $options;
        $this->actions = $actions;
        $this->footer = $footer;
    }

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

    protected function processStep($input, $step, UssdSession $session): UssdResponse
    {
        if ($step === 0) {
            $input = trim($input);

            if (!isset($this->options[$input])) {
                return UssdResponse::continue("Invalid option. Please try again.\n".$this->showInitial($session)->getMessage());
            }

            if (isset($this->actions[$input])) {
                $action = $this->actions[$input];

                if ($action instanceof ActionInterface) {
                    return $action->execute($input, $session, $this->framework);
                } elseif (is_callable($action)) {
                    return $action($session, $this->framework, $input);
                }
            }

            return UssdResponse::end('Thank you for using our service!');
        }

        return UssdResponse::end('Session ended.');
    }

    public function addOption($key, $value, $action = null): static
    {
        $this->options[$key] = $value;
        if ($action) {
            $this->actions[$key] = $action;
        }

        return $this;
    }

    public function setFooter($footer): static
    {
        $this->footer = $footer;

        return $this;
    }
}
