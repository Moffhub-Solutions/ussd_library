<?php

namespace Moffhub\Ussd\Builders;

use Closure;
use Moffhub\Ussd\Menus\UssdMenu;

class FlexibleFormBuilder
{
    protected array $fields = [];

    protected array $config = [];

    public function __construct(protected string $title, protected ?Closure $onComplete = null) {}

    public function textField(string $name, string $prompt, array $options = []): self
    {
        $this->fields[$name] = array_merge([
            'type' => 'text',
            'prompt' => $prompt,
        ], $options);

        return $this;
    }

    public function numberField(string $name, string $prompt, array $options = []): self
    {
        $this->fields[$name] = array_merge([
            'type' => 'number',
            'prompt' => $prompt,
        ], $options);

        return $this;
    }

    public function dateField(string $name, string $prompt, array $options = []): self
    {
        $this->fields[$name] = array_merge([
            'type' => 'date',
            'prompt' => $prompt,
        ], $options);

        return $this;
    }

    public function selectField(string $name, string $prompt, array $options, array $config = []): self
    {
        $this->fields[$name] = array_merge([
            'type' => 'select',
            'prompt' => $prompt,
            'options' => $options,
        ], $config);

        return $this;
    }

    public function paginatedField(string $name, string $prompt, mixed $dataProvider, array $config = []): self
    {
        $this->fields[$name] = array_merge([
            'type' => 'paginated',
            'prompt' => $prompt,
            'data_provider' => $dataProvider,
            'items_per_page' => 5,
        ], $config);

        return $this;
    }

    public function searchableField(string $name, string $prompt, mixed $dataProvider, array $searchFields = ['name'], array $config = []): self
    {
        $this->fields[$name] = array_merge([
            'type' => 'searchable_paginated',
            'prompt' => $prompt,
            'data_provider' => $dataProvider,
            'search_fields' => $searchFields,
            'items_per_page' => 5,
        ], $config);

        return $this;
    }

    public function conditionalField(string $name, string $prompt, callable $condition, array $options = []): self
    {
        $this->fields[$name] = array_merge([
            'type' => 'conditional',
            'prompt' => $prompt,
            'condition' => $condition,
        ], $options);

        return $this;
    }

    public function setFieldValidator(string $fieldName, callable $validator): self
    {
        if (isset($this->fields[$fieldName])) {
            $this->fields[$fieldName]['validator'] = $validator;
        }

        return $this;
    }

    public function setFieldOptional(string $fieldName, bool $optional = true): self
    {
        if (isset($this->fields[$fieldName])) {
            $this->fields[$fieldName]['optional'] = $optional;
        }

        return $this;
    }

    public function enableFieldValidation(): self
    {
        $this->config['enable_validation'] = true;

        return $this;
    }

    public function enableProgressTracking(): self
    {
        $this->config['show_progress'] = true;

        return $this;
    }

    public function enableContextPreservation(): self
    {
        $this->config['preserve_context'] = true;

        return $this;
    }

    public function build(): UssdMenu
    {
        $menu = new UssdMenu($this->title);
        $menu->setType('flexible_form');
        $menu->setFields($this->fields);
        $menu->setOnComplete($this->onComplete);
        $menu->setConfig($this->config);

        $features = ['validation'];

        foreach ($this->fields as $field) {
            if (in_array($field['type'], ['paginated', 'searchable_paginated'])) {
                $features[] = 'pagination';
            }
            if ($field['type'] === 'searchable_paginated') {
                $features[] = 'search';
            }
            if (isset($field['condition'])) {
                $features[] = 'conditional';
            }
        }

        if ($this->config['preserve_context'] ?? false) {
            $features[] = 'context_snapshots';
        }

        $menu->enableFeatures(array_unique($features));

        return $menu;
    }
}
