<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Helpers;

use Moffhub\Ussd\Interfaces\ValidatorInterface;

class FormField
{
    protected string $type;

    protected array $config;

    protected array $validators = [];

    public mixed $options;

    protected array $dependencies = [];

    public function __construct(public string $name, public string $prompt, array $config = [])
    {
        $this->type = $config['type'] ?? 'text';
        $this->config = $config;

        if (isset($config['validator'])) {
            $this->validators = is_array($config['validator']) ? $config['validator'] : [$config['validator']];
        }

        $this->options = $config['options'] ?? null;
        $this->dependencies = $config['dependencies'] ?? [];
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getPrompt(): string
    {
        return $this->prompt;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getConfig(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->config;
        }

        return $this->config[$key] ?? null;
    }

    public function getOptions(mixed $session = null): mixed
    {
        if (is_callable($this->options)) {
            return call_user_func($this->options, $session);
        }

        return $this->options;
    }

    public function getDependencies(): array
    {
        return $this->dependencies;
    }

    public function validate(string $input): mixed
    {
        foreach ($this->validators as $validator) {
            if ($validator instanceof ValidatorInterface) {
                $result = $validator->validate($input);
                if ($result !== true) {
                    return $result;
                }
            } elseif (is_callable($validator)) {
                $result = $validator($input);
                if ($result !== true) {
                    return $result;
                }
            }
        }

        return true;
    }

    public function isVisible(array $formData = []): bool
    {
        if ($this->dependencies === []) {
            return true;
        }

        foreach ($this->dependencies as $dependency) {
            $field = $dependency['field'];
            $condition = $dependency['condition'];
            $value = $dependency['value'];

            $fieldValue = $formData[$field] ?? null;

            switch ($condition) {
                case 'equals':
                    if ($fieldValue != $value) {
                        return false;
                    }
                    break;
                case 'not_equals':
                    if ($fieldValue == $value) {
                        return false;
                    }
                    break;
                case 'in':
                    if (! in_array($fieldValue, $value)) {
                        return false;
                    }
                    break;
                case 'not_empty':
                    if (empty($fieldValue)) {
                        return false;
                    }
                    break;
            }
        }

        return true;
    }

    public function isPaginated(): bool
    {
        return in_array($this->type, ['paginated', 'searchable_paginated']);
    }

    public function isSearchable(): bool
    {
        return $this->type === 'searchable_paginated';
    }

    public function getSearchFields(): array
    {
        return $this->config['search_fields'] ?? ['name'];
    }

    public function isOptional(): bool
    {
        return $this->config['optional'] ?? false;
    }

    public function getItemsPerPage(): int
    {
        return $this->config['items_per_page'] ?? 5;
    }

    public function getItemFormatter(): callable
    {
        return $this->config['item_formatter'] ?? function ($key, $item) {
            if (is_array($item)) {
                return $item['name'] ?? $item['title'] ?? json_encode($item);
            }

            return (string) $item;
        };
    }
}
