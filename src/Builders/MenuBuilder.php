<?php

namespace Moffhub\Ussd\Builders;

use Closure;
use Moffhub\Ussd\Menus\SimpleMenu;

class MenuBuilder
{
    protected string $title = '';

    protected array $options = [];

    protected array $actions = [];

    protected ?Closure $onComplete = null;

    protected array $config = [];

    public function __construct(protected string $name) {}

    public function title(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function option(string $key, string $value, ?callable $action = null): self
    {
        $this->options[$key] = $value;
        if ($action) {
            $this->actions[$key] = $action;
        }

        return $this;
    }

    public function options(array $options): self
    {
        // Use array union (+) instead of array_merge to preserve string numeric keys
        $this->options = $this->options + $options;

        return $this;
    }

    public function action(string $key, callable $action): self
    {
        $this->actions[$key] = $action;

        return $this;
    }

    public function onComplete(Closure $callback): self
    {
        $this->onComplete = $callback;

        return $this;
    }

    public function config(array $config): self
    {
        $this->config = array_merge($this->config, $config);

        return $this;
    }

    public function build(): SimpleMenu
    {
        $menu = new SimpleMenu($this->title, $this->options, $this->actions);

        if ($this->onComplete instanceof \Closure) {
            $menu->setOnComplete($this->onComplete);
        }

        if ($this->config !== []) {
            $menu->setConfig($this->config);
        }

        return $menu;
    }
}
