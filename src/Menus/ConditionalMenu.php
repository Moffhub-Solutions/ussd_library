<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Menus;

use Moffhub\Ussd\UssdResponse;
use Moffhub\Ussd\UssdSession;

class ConditionalMenu extends UssdMenu
{
    protected array $conditions = [];
    public mixed $defaultMenu;

    public function __construct(mixed $defaultMenu = null)
    {
        parent::__construct('Conditional Menu');
        $this->defaultMenu = $defaultMenu;
    }

    public function addCondition(callable $condition, mixed $menu): self
    {
        $this->conditions[] = ['condition' => $condition, 'menu' => $menu];

        return $this;
    }

    protected function showInitial(UssdSession $session): UssdResponse
    {
        $menu = $this->resolveMenu($session);
        if ($menu) {
            $menu->setFramework($this->framework);

            return $menu->process('', $session);
        }

        return UssdResponse::end('No menu available.');
    }

    protected function processStep($input, $step, UssdSession $session): UssdResponse
    {
        $menu = $this->resolveMenu($session);
        if ($menu) {
            $menu->setFramework($this->framework);

            return $menu->process($input, $session);
        }

        return UssdResponse::end('Session ended.');
    }

    protected function resolveMenu(UssdSession $session): mixed
    {
        foreach ($this->conditions as $item) {
            $condition = $item['condition'];

            if (is_callable($condition)) {
                if ($condition($session, $this->framework)) {
                    return $item['menu'];
                }
            } elseif (is_bool($condition) && $condition) {
                return $item['menu'];
            }
        }

        return $this->defaultMenu;
    }
}
