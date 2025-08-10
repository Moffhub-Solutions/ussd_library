<?php

namespace Moffhub\Ussd\Builders;

class UnifiedMenuBuilder
{
    protected UssdMenu $menu;
    protected UssdBuilder $builder;

    public function __construct(UssdMenu $menu, UssdBuilder $builder)
    {
        $this->menu = $menu;
        $this->builder = $builder;
    }

    public function setType(string $type): self
    {
        $this->menu->setType($type);
        return $this;
    }

    public function addField(string $name, array $config): self
    {
        $this->menu->addField($name, $config);
        return $this;
    }

    public function addOption(string $key, string $value, ?callable $action = null): self
    {
        $this->menu->addOption($key, $value, $action);
        return $this;
    }

    public function setDataProvider(mixed $dataProvider): self
    {
        $this->menu->setDataProvider($dataProvider);
        return $this;
    }

    public function enablePagination(int $itemsPerPage = 5): self
    {
        $this->menu->enableFeature('pagination');
        $this->menu->setConfig(['items_per_page' => $itemsPerPage]);
        return $this;
    }

    public function enableSearch(array $searchFields = ['name']): self
    {
        $this->menu->enableFeature('search');
        $this->menu->setConfig(['search_fields' => $searchFields]);
        return $this;
    }

    public function enableValidation(): self
    {
        $this->menu->enableFeature('validation');
        return $this;
    }

    public function enableContextSnapshots(): self
    {
        $this->menu->enableFeature('context_snapshots');
        return $this;
    }

    public function enableAnalytics(): self
    {
        $this->menu->enableFeature('analytics');
        return $this;
    }

    public function setOnComplete(callable $callback): self
    {
        $this->menu->setOnComplete($callback);
        return $this;
    }

    public function setItemFormatter(callable $formatter): self
    {
        $this->menu->setItemFormatter($formatter);
        return $this;
    }

    public function setValidator(callable $validator): self
    {
        $this->menu->setValidator($validator);
        return $this;
    }

    public function addCondition(callable $condition, mixed $action): self
    {
        $this->menu->addCondition($condition, $action);
        return $this;
    }

    public function setConfig(array $config): self
    {
        $this->menu->setConfig($config);
        return $this;
    }

    public function build(): UssdBuilder
    {
        return $this->builder;
    }
}
