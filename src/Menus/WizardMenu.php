<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Menus;
use Moffhub\Ussd\Interfaces\ActionInterface;
use Moffhub\Ussd\UssdResponse;
use Moffhub\Ussd\UssdSession;

class WizardMenu extends UssdMenu
{
    protected string $title;
    protected mixed $steps = [];
    protected int $currentStepIndex = 0;
    protected $onComplete;

    public function __construct($title, $steps = [], $onComplete = null)
    {
        parent::__construct($title);
        $this->title = $title;
        $this->steps = $steps;
        $this->onComplete = $onComplete;
    }

    public function addStep($name, $menu)
    {
        $this->steps[$name] = $menu;

        return $this;
    }

    protected function showInitial(UssdSession $session): UssdResponse
    {
        $session->setMenuData(['current_step' => 0, 'completed_steps' => []]);

        return $this->showCurrentStep($session);
    }

    protected function processStep($input, $step, UssdSession $session): UssdResponse
    {
        return $this->showCurrentStep($session, $input);
    }

    protected function showCurrentStep(UssdSession $session, $input = '')
    {
        $menuData = $session->getMenuData();
        $currentStepIndex = $menuData['current_step'] ?? 0;
        $stepNames = array_keys($this->steps);

        if (!isset($stepNames[$currentStepIndex])) {
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
        if ($this->onComplete) {
            if ($this->onComplete instanceof ActionInterface) {
                return $this->onComplete->execute(null, $session, $this->framework);
            } elseif (is_callable($this->onComplete)) {
                return ($this->onComplete)($session, $this->framework);
            }
        }

        return UssdResponse::end('Wizard completed successfully!');
    }
}
