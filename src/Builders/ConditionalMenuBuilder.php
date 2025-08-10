<?php

namespace Moffhub\Ussd\Builders;

use Moffhub\Ussd\Menus\ConditionalMenu;

class ConditionalMenuBuilder
{
    protected ConditionalMenu $menu;

    protected UssdBuilder $builder;

    public function __construct(ConditionalMenu $menu, UssdBuilder $builder)
    {
        $this->menu = $menu;
        $this->builder = $builder;
    }

    public function when(callable $condition, mixed $menu): self
    {
        $this->menu->addCondition($condition, $menu);

        return $this;
    }

    public function otherwise(mixed $menu): self
    {
        $this->menu->setDefaultMenu($menu);

        return $this;
    }

    public function build(): UssdBuilder
    {
        return $this->builder;
    }
}
