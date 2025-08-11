<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Menus;

use Closure;
use Moffhub\Ussd\UssdResponse;
use Moffhub\Ussd\UssdSession;

class WizardMenu extends UssdMenu
{
    protected string $title;

    protected mixed $steps = [];

    protected int $currentStepIndex = 0;

    protected ?Closure $onComplete;

    public function __construct(string $title, array $steps = [], ?Closure $onComplete = null)
    {
        parent::__construct($title);
        $this->title = $title;
        $this->steps = $steps;
        $this->onComplete = $onComplete;
    }

    public function addStep(string $name, UssdMenu $menu): self
    {
        $this->steps[$name] = $menu;

        return $this;
    }

    protected function showInitial(UssdSession $session): UssdResponse
    {
        $session->setMenuData(['current_step' => 0, 'completed_steps' => []]);

        return $this->showCurrentStep($session);
    }

    protected function processStep(string $input, string $step, UssdSession $session): UssdResponse
    {
        return $this->showCurrentStep($session, $input);
    }

    protected function showCurrentStep(UssdSession $session, string $input = ''): UssdResponse
    {
        $menuData = $session->getMenuData();
        $currentStepIndex = $menuData['current_step'] ?? 0;
        $stepNames = array_keys($this->steps);

        if (! isset($stepNames[$currentStepIndex])) {
            return $this->completeWizard($session);
        }

        $currentStepName = $stepNames[$currentStepIndex];
        $currentStepMenu = $this->steps[$currentStepName];

        $currentStepMenu->setFramework($this->framework);
        $response = $currentStepMenu->process($input, $session);

        if ($response->isEnd()) {
            $completedSteps = $menuData['completed_steps'] ?? [];
            $completedSteps[] = $currentStepName;

            $session->setMenuData([
                'current_step' => $currentStepIndex + 1,
                'completed_steps' => $completedSteps,
            ]);

            return $this->showCurrentStep($session);
        }

        return $response;
    }

    protected function completeWizard(UssdSession $session): UssdResponse
    {
        if ($this->onComplete && $this->framework) {
            $result = call_user_func($this->onComplete, $session, $this->framework);
            if ($result instanceof UssdResponse) {
                return $result;
            }
            if (is_string($result)) {
                return UssdResponse::end($result);
            }
        }

        return UssdResponse::end('Wizard completed successfully!');
    }
}
