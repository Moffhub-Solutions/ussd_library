<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Menus;

use Moffhub\Ussd\Interfaces\ActionInterface;
use Moffhub\Ussd\Interfaces\UssdMenuInterface;
use Moffhub\Ussd\UssdResponse;
use Moffhub\Ussd\UssdSession;

class ConditionalMenu extends UssdMenu
{
    protected array $conditions = [];

    public function __construct(public mixed $defaultMenu = null)
    {
        parent::__construct('Conditional Menu');
    }

    #[\Override]
    public function addCondition(callable $condition, mixed $action): self
    {
        $this->conditions[] = ['condition' => $condition, 'action' => $action];

        return $this;
    }

    /**
     * Set the fallback branch used when no condition matches. Accepts a menu
     * instance, a registered menu name, or an inline action (overrides the base
     * implementation, which is typed string-only and requires the framework to
     * already be set - neither holds at build time when otherwise() is called).
     */
    #[\Override]
    public function setDefaultMenu(mixed $menu): static
    {
        $this->defaultMenu = $menu;

        return $this;
    }

    #[\Override]
    protected function showInitial(UssdSession $session): UssdResponse
    {
        return $this->runResolvedBranch('', $session, 'No menu available.');
    }

    protected function processStep(string $input, int $step, UssdSession $session): UssdResponse
    {
        return $this->runResolvedBranch($input, $session, 'Session ended.');
    }

    /**
     * Resolve the branch selected by the matching condition (or the default) and
     * run it. A branch registered via when()/otherwise() may be any of:
     *   - a menu instance (UssdMenuInterface)        -> setFramework() + process()
     *   - a registered menu name (string)            -> framework->getMenu() then process()
     *   - an inline action (ActionInterface), e.g.
     *     UssdHelpers::navigateAction()/callbackAction() -> execute()
     */
    protected function runResolvedBranch(string $input, UssdSession $session, string $fallbackMessage): UssdResponse
    {
        $branch = $this->resolveMenu($session);

        if (is_string($branch) && $this->framework) {
            $branch = $this->framework->getMenu($branch);
        }

        if ($branch instanceof ActionInterface) {
            return $branch->execute($input === '' ? null : $input, $session, $this->framework);
        }

        if ($branch instanceof UssdMenuInterface) {
            $branch->setFramework($this->framework);

            return $branch->process($input, $session);
        }

        return UssdResponse::end($fallbackMessage);
    }

    protected function resolveMenu(UssdSession $session): mixed
    {
        foreach ($this->conditions as $item) {
            $condition = $item['condition'];

            if (is_callable($condition)) {
                if ($condition($session, $this->framework)) {
                    return $item['action'];
                }
            } elseif (is_bool($condition) && $condition) {
                return $item['action'];
            }
        }

        return $this->defaultMenu;
    }
}
