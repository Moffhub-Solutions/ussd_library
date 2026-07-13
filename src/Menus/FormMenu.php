<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Menus;

use Closure;
use Exception;
use Moffhub\Ussd\Helpers\FormField;
use Moffhub\Ussd\UssdFramework;
use Moffhub\Ussd\UssdResponse;
use Moffhub\Ussd\UssdSession;

class FormMenu extends UssdMenu
{
    protected string $title;

    /** @var array<string, FormField> */
    protected array $fields = [];

    public function __construct(string $title, array $fields = [], protected ?Closure $onComplete = null)
    {
        parent::__construct($title);
        $this->title = $title;
        $this->setFields($fields);
    }

    #[\Override]
    public function setFields(array $fields): static
    {
        foreach ($fields as $name => $config) {
            if ($config instanceof FormField) {
                $this->fields[$name] = $config;
            } else {
                $prompt = $config['prompt'] ?? "Enter $name:";
                $this->fields[$name] = new FormField($name, $prompt, $config);
            }
        }

        return $this;
    }

    /**
     * Route input through this form's own step-based collection rather than the
     * base menu's type dispatch (which, without an explicit 'form' type, falls
     * back to simple-menu handling and rejects every answer as an invalid option).
     */
    #[\Override]
    public function process(string $input, UssdSession $session): UssdResponse
    {
        $navResponse = $this->handleGlobalNavigation($input, $session);

        if ($navResponse instanceof UssdResponse) {
            return $navResponse;
        }

        $step = $session->getStep();

        if (($input === '' || $input === '0') && $step === 0) {
            return $this->showInitial($session);
        }

        return $this->processStep($input, $step, $session);
    }

    #[\Override]
    protected function showInitial(UssdSession $session): UssdResponse
    {
        if ($this->fields === []) {
            return UssdResponse::end('No fields defined for this form.');
        }

        $session->setStep(0);

        $firstField = reset($this->fields);
        $message = $this->title !== '' && $this->title !== '0' ? $this->title."\n\n".$firstField->prompt : $firstField->prompt;
        $message = $this->addGlobalNavigation($message, $session);

        return UssdResponse::continue($message);
    }

    protected function processStep(string $input, int $step, UssdSession $session): UssdResponse
    {
        $navigation = $this->config['navigation'] ?? [];
        $navCommands = array_filter([
            $navigation['back'] ?? '99',
            $navigation['home'] ?? '0',
        ], static fn ($command): bool => $command !== '');

        if (in_array($input, $navCommands)) {
            $navResponse = $this->processGlobalNavigation($input, $session);
            if ($navResponse instanceof UssdResponse) {
                return $navResponse;
            }
        }

        $fieldKeys = array_keys($this->fields);

        if ($step >= count($fieldKeys)) {
            return $this->completeForm($session);
        }

        $fieldKey = $fieldKeys[$step];
        $field = $this->fields[$fieldKey];

        if ($input === '' || $input === '0') {
            if ($step === 0) {
                $message = $field->prompt;
                $message = $this->addGlobalNavigation($message, $session);

                return UssdResponse::continue($message);
            }

            if ($field->isOptional()) {
                $session->setFormData($fieldKey, '');

                return $this->moveToNextField($session);
            }

            $message = "This field is required.\n\n".$field->prompt;
            $message = $this->addGlobalNavigation($message, $session);

            return UssdResponse::continue($message);
        }

        $validation = $field->validate($input);
        if ($validation !== true) {
            $message = $validation."\n\n".$field->prompt;
            $message = $this->addGlobalNavigation($message, $session);

            return UssdResponse::continue($message);
        }

        if ($field->isPaginated()) {
            return $this->handlePaginatedField($field, $input, $session);
        }

        $session->setFormData($fieldKey, $input);

        return $this->moveToNextField($session);
    }

    #[\Override]
    protected function moveToNextField(UssdSession $session): UssdResponse
    {
        $currentStep = $session->getStep();
        $nextStep = $currentStep + 1;
        $fieldKeys = array_keys($this->fields);

        if ($nextStep >= count($fieldKeys)) {
            return $this->completeForm($session);
        }

        $session->setStep($nextStep);

        $nextFieldKey = $fieldKeys[$nextStep];
        $nextField = $this->fields[$nextFieldKey];

        // Keep the form title on every step, not just the first, so the caller
        // always sees which form they are filling in.
        $message = $this->title !== '' && $this->title !== '0'
            ? $this->title."\n\n".$nextField->prompt
            : $nextField->prompt;
        $message = $this->addGlobalNavigation($message, $session);

        return UssdResponse::continue($message);
    }

    #[\Override]
    protected function handlePaginatedField(FormField $field, string $input, UssdSession $session): UssdResponse
    {
        try {
            $framework = $this->framework;
            $menuName = '__form_paginated_'.$field->name;

            $options = is_callable($field->options)
                ? call_user_func($field->options, $session)
                : $field->options;

            if (empty($options)) {
                $message = "No options available for this field.\n\n".$field->prompt;
                $message = $this->addGlobalNavigation($message, $session);

                return UssdResponse::continue($message);
            }
            if (! $framework instanceof UssdFramework) {
                throw new Exception('Framework is not set for paginated menu.');
            }

            $paginatedMenu = new PaginatedMenu(
                $field->prompt,
                $options,
                [
                    'item_formatter' => function ($key, $item) {
                        if (is_array($item)) {
                            return $item['name'] ?? json_encode($item);
                        }

                        return (string) $item;
                    },
                    'item_action' => function ($key, $item, $session, $framework) use ($field) {
                        $value = is_array($item) && isset($item['id']) ? $item['id'] : $key;
                        $session->setFormData($field->name, $value);

                        $formMenu = $framework->getMenu($session->getCurrentMenu());

                        return $formMenu->moveToNextField($session);
                    },
                ]
            );
            $paginatedMenu->setFramework($framework);
            $paginatedMenu->setConfig($this->config);

            $framework->registerMenu($menuName, $paginatedMenu);
            $framework->navigateToMenu($menuName);

            return $paginatedMenu->process($input, $session);

        } catch (\Throwable $e) {
            $this->reportInteractiveError($e, 'paginated field "'.$field->name.'"');

            $message = "Error processing field options. Please try again.\n\n".$field->prompt;
            $message = $this->addGlobalNavigation($message, $session);

            return UssdResponse::continue($message);
        }
    }

    #[\Override]
    protected function completeForm(UssdSession $session): UssdResponse
    {
        try {
            $formData = $session->getFormData();

            if ($this->onComplete && $this->framework) {
                // Match UssdMenu::completeForm's argument order so a single onComplete
                // signature ($session, $formData) works regardless of which menu runs it.
                return call_user_func($this->onComplete, $session, $formData);
            }

            return UssdResponse::end('Form completed successfully!');

        } catch (\Throwable $e) {
            $this->reportInteractiveError($e, 'form completion (onComplete)');

            return UssdResponse::end('Form completion error. Please try again.');
        }
    }

    #[\Override]
    protected function handleBackNavigation(UssdSession $session): ?UssdResponse
    {
        $currentStep = $session->getStep();

        if ($currentStep <= 0) {
            return parent::processGlobalNavigation('99', $session);
        }

        $prevStep = $currentStep - 1;
        $session->setStep($prevStep);

        $fieldKeys = array_keys($this->fields);
        $prevFieldKey = $fieldKeys[$prevStep];
        $prevField = $this->fields[$prevFieldKey];

        $message = "Back to previous field:\n\n".$prevField->prompt;
        $message = $this->addGlobalNavigation($message, $session);

        return UssdResponse::continue($message);
    }

    protected function processGlobalNavigation(string $input, UssdSession $session): ?UssdResponse
    {
        $navigation = $this->config['navigation'] ?? [];

        if ($input === ($navigation['back'] ?? '99')) {
            return $this->handleBackNavigation($session);
        }

        return parent::processGlobalNavigation($input, $session);
    }

    protected function getFormProgress(UssdSession $session): int
    {
        $totalFields = count($this->fields);
        $currentStep = $session->getStep();

        if ($totalFields === 0) {
            return 100;
        }

        return min(100, (int) (($currentStep / $totalFields) * 100));
    }

    protected function addProgressIndicator(string $message, UssdSession $session): string
    {
        $showProgress = $this->config['form']['show_progress'] ?? false;

        if (! $showProgress) {
            return $message;
        }

        $progress = $this->getFormProgress($session);
        $progressText = "Progress: $progress%";

        return $progressText."\n\n".$message;
    }
}
