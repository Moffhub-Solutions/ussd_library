<?php

namespace Moffhub\Ussd\Menus;

use Closure;
use Exception;
use Illuminate\Support\Facades\Log;
use Moffhub\Ussd\Helpers\FormField;
use Moffhub\Ussd\Interfaces\ActionInterface;
use Moffhub\Ussd\Interfaces\UssdMenuInterface;
use Moffhub\Ussd\Traits\GlobalNavigationTrait;
use Moffhub\Ussd\UssdFramework;
use Moffhub\Ussd\UssdResponse;
use Moffhub\Ussd\UssdSession;

class UssdMenu implements UssdMenuInterface
{
    use GlobalNavigationTrait;

    protected ?UssdFramework $framework = null;

    protected string $type = 'simple'; // simple, form, enhanced_form, paginated, searchable, conditional, wizard

    protected array $config = [
        'items_per_page' => 5,
        'search_fields' => ['name'],
        'enable_validation' => true,
        'show_progress' => false,
        'preserve_context' => false,
        'enable_back_navigation' => true,
        'navigation_commands' => ['99' => 'back', '0' => 'home', '98' => 'search', '00' => 'next'],
    ];

    protected array $options = [];

    protected array $actions = [];

    /** @var array<string, FormField> */
    protected array $fields = [];

    protected array $conditions = [];

    protected array $validators = [];

    protected ?Closure $onComplete = null;

    protected mixed $dataProvider = null;

    protected ?Closure $itemFormatter = null;

    protected array $enabledFeatures = [];

    public function __construct(protected string $title) {}

    public function setFramework(UssdFramework $framework): void
    {
        $this->framework = $framework;
        $this->config = array_merge($this->config, $framework->getConfig());
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function setConfig(array $config): self
    {
        $this->config = array_merge($this->config, $config);

        return $this;
    }

    public function enableFeature(string $feature): self
    {
        if (! in_array($feature, $this->enabledFeatures)) {
            $this->enabledFeatures[] = $feature;
        }

        return $this;
    }

    public function enableFeatures(array $features): self
    {
        foreach ($features as $feature) {
            $this->enableFeature($feature);
        }

        return $this;
    }

    public function disableFeature(string $feature): self
    {
        $this->enabledFeatures = array_filter($this->enabledFeatures, fn ($f): bool => $f !== $feature);

        return $this;
    }

    public function isFeatureEnabled(string $feature): bool
    {
        return in_array($feature, $this->enabledFeatures);
    }

    public function setOptions(array $options): self
    {
        $this->options = $options;

        return $this;
    }

    public function addOption(string $key, string $value, ?callable $action = null): self
    {
        $this->options[$key] = $value;
        if ($action) {
            $this->actions[$key] = $action;
        }

        return $this;
    }

    public function setActions(array $actions): self
    {
        $this->actions = $actions;

        return $this;
    }

    public function addAction(string $key, callable $action): self
    {
        $this->actions[$key] = $action;

        return $this;
    }

    public function setDefaultMenu(string $menu): static
    {
        if ($this->framework instanceof UssdFramework) {
            $this->conditions[] = [
                'condition' => fn (UssdSession $session): true => true,
                'action' => $menu,
            ];
        } else {
            throw new Exception('Framework is not set. Cannot set default menu.');
        }

        return $this;
    }

    public function setFields(array $fields): self
    {
        $this->fields = [];
        foreach ($fields as $name => $config) {
            $this->addField($name, $config);
        }

        return $this;
    }

    public function addField(string $name, FormField|array $config): self
    {
        if ($config instanceof FormField) {
            $this->fields[$name] = $config;
        } else {
            $prompt = $config['prompt'] ?? "Enter $name:";
            $this->fields[$name] = new FormField($name, $prompt, $config);
        }

        return $this;
    }

    public function setDataProvider(mixed $dataProvider): self
    {
        $this->dataProvider = $dataProvider;

        return $this;
    }

    public function setItemFormatter(Closure $formatter): self
    {
        $this->itemFormatter = $formatter;

        return $this;
    }

    public function setOnComplete(?Closure $onComplete): self
    {
        $this->onComplete = $onComplete;

        return $this;
    }

    public function setValidator(callable $validator): self
    {
        $this->validators[] = $validator;

        return $this;
    }

    public function addCondition(callable $condition, mixed $action): self
    {
        $this->conditions[] = ['condition' => $condition, 'action' => $action];

        return $this;
    }

    protected function getNavigationCommand(string $type): ?string
    {
        return $this->config['navigation'][$type] ?? null;
    }

    protected function getType(): string
    {
        return $this->type;
    }

    public function process(string $input, UssdSession $session): UssdResponse
    {
        $step = $session->getStep();

        // Check global navigation first (home/back) before form-specific handling
        $navResponse = $this->handleGlobalNavigation($input, $session);
        if ($navResponse instanceof UssdResponse) {
            return $navResponse;
        }

        if (($input === '' || $input === '0') && $step === 0) {
            return $this->showInitial($session);
        }

        if (($input === '' || $input === '0') && $step > 0) {
            return $this->handleEmptyInput($step, $session);
        }

        return match ($this->type) {
            'simple' => $this->processSimpleMenu($input, $session),
            'form', 'enhanced_form', 'flexible_form' => $this->processFormMenu($input, $session),
            'paginated', 'searchable' => $this->processPaginatedMenu($input, $session),
            'conditional' => $this->processConditionalMenu($input, $session),
            'wizard' => $this->processWizardMenu($input, $session),
            default => $this->processSimpleMenu($input, $session),
        };
    }

    public function display(UssdSession $session): UssdResponse
    {
        return $this->showInitial($session);
    }

    protected function showInitial(UssdSession $session): UssdResponse
    {
        $session->setStep(0);

        return match ($this->type) {
            'simple' => $this->displaySimpleMenu($session),
            'form', 'enhanced_form', 'flexible_form' => $this->displayFormMenu($session),
            'paginated', 'searchable' => $this->displayPaginatedMenu($session),
            'conditional' => $this->displayConditionalMenu($session),
            'wizard' => $this->displayWizardMenu($session),
            default => $this->displaySimpleMenu($session),
        };
    }

    protected function handleEmptyInput(int $step, UssdSession $session): UssdResponse
    {
        if (in_array($this->type, ['form', 'enhanced_form', 'flexible_form'])) {
            return $this->handleEmptyFormInput($step, $session);
        }

        return $this->display($session);
    }

    protected function processSimpleMenu(string $input, UssdSession $session): UssdResponse
    {
        $input = trim($input);

        if ($this->framework instanceof UssdFramework) {
            if (isset($this->actions[$input])) {
                $action = $this->actions[$input];
                if ($action instanceof ActionInterface) {
                    return $action->execute($input, $session, $this->framework);
                }

                if (is_callable($action)) {
                    return $action($input, $session, $this->framework);
                }
            }

            if (isset($this->options[$input])) {
                return UssdResponse::end('You selected: '.$this->options[$input]);
            }
        }

        return $this->handleInvalidInput($input, $session);
    }

    protected function displaySimpleMenu(UssdSession $session): UssdResponse
    {
        $message = $this->title;

        if ($this->options !== []) {
            $message .= "\n\n";
            foreach ($this->options as $key => $value) {
                $message .= "$key. $value\n";
            }
        }

        $message = $this->addGlobalNavigation($message, $session);

        return UssdResponse::continue($message);
    }

    protected function processFormMenu(string $input, UssdSession $session): UssdResponse
    {
        $formState = $session->getFormData('_form_state', 'collecting');
        $session->getFormData('_form_field_index', 0);

        return match ($formState) {
            'collecting' => $this->handleFieldInput($input, $session),
            'paginating' => $this->handleFormPaginationInput($input, $session),
            'searching' => $this->handleFormSearchInput($input, $session),
            default => $this->handleFieldInput($input, $session),
        };
    }

    protected function displayFormMenu(UssdSession $session): UssdResponse
    {
        if ($this->fields === []) {
            return UssdResponse::end('No fields defined for this form.');
        }

        $session->setFormData('_form_field_index', 0);
        $session->setFormData('_form_state', 'collecting');
        $session->setFormData('_pagination_page', 1);
        $session->setFormData('_search_query', '');

        return $this->showCurrentField($session);
    }

    protected function handleFieldInput(string $input, UssdSession $session): UssdResponse
    {
        $fieldIndex = $session->getFormData('_form_field_index', 0);
        $fieldKeys = array_keys($this->fields);

        if (! isset($fieldKeys[$fieldIndex])) {
            return $this->completeForm($session);
        }

        $fieldKey = $fieldKeys[$fieldIndex];
        $field = $this->fields[$fieldKey];

        if ($field->isPaginated()) {
            return $this->handlePaginatedField($field, $input, $session);
        }

        if ($input === '' || $input === '0') {
            if ($field->isOptional()) {
                $session->setFormData($fieldKey, '');

                return $this->moveToNextField($session);
            }

            $message = "This field is required.\n\n".$field->getPrompt();
            $message = $this->addGlobalNavigation($message, $session);

            return UssdResponse::continue($message);
        }

        if ($this->isFeatureEnabled('validation')) {
            $validation = $field->validate($input);
            if ($validation !== true) {
                $message = $validation."\n\n".$field->getPrompt();
                $message = $this->addGlobalNavigation($message, $session);

                return UssdResponse::continue($message);
            }
        }

        $session->setFormData($fieldKey, $input);

        if ($this->isFeatureEnabled('context_snapshots')) {
            $session->createContextSnapshot("field_{$fieldKey}_completed");
        }

        return $this->moveToNextField($session);
    }

    protected function handleEmptyFormInput(int $step, UssdSession $session): UssdResponse
    {
        $fieldIndex = $session->getFormData('_form_field_index', 0);
        $fieldKeys = array_keys($this->fields);

        if (isset($fieldKeys[$fieldIndex])) {
            $field = $this->fields[$fieldKeys[$fieldIndex]];
            $message = $field->getPrompt();
            $message = $this->addGlobalNavigation($message, $session);

            return UssdResponse::continue($message);
        }

        return $this->showCurrentField($session);
    }

    protected function showCurrentField(UssdSession $session): UssdResponse
    {
        $fieldIndex = $session->getFormData('_form_field_index', 0);
        $fieldKeys = array_keys($this->fields);

        while ($fieldIndex < count($fieldKeys)) {
            $fieldKey = $fieldKeys[$fieldIndex];
            $field = $this->fields[$fieldKey];

            if ($field->isVisible($session->getFormData())) {
                break;
            }

            $fieldIndex++;
            $session->setFormData('_form_field_index', $fieldIndex);
        }

        if ($fieldIndex >= count($fieldKeys)) {
            return $this->completeForm($session);
        }

        $fieldKey = $fieldKeys[$fieldIndex];
        $field = $this->fields[$fieldKey];

        if ($field->isPaginated()) {
            $session->setFormData('_form_state', 'paginating');

            return $this->showPaginatedFieldOptions($field, $session);
        }

        $message = $field->getPrompt();

        if ($this->config['show_progress'] ?? false) {
            $progress = $this->calculateFormProgress($session);
            $message = "Progress: $progress%\n\n".$message;
        }

        $message = $this->addGlobalNavigation($message, $session);

        return UssdResponse::continue($message);
    }

    protected function moveToNextField(UssdSession $session): UssdResponse
    {
        $currentIndex = $session->getFormData('_form_field_index', 0);
        $session->setFormData('_form_field_index', $currentIndex + 1);

        return $this->showCurrentField($session);
    }

    protected function completeForm(UssdSession $session): UssdResponse
    {
        try {
            $formData = $session->getFormData();

            if ($this->onComplete && $this->framework) {
                return call_user_func($this->onComplete, $session, $formData);
            }

            return UssdResponse::end('Form completed successfully!');

        } catch (\Throwable $e) {
            $this->reportInteractiveError($e, 'form completion (onComplete)');

            return UssdResponse::end('Form completion error. Please try again.');
        }
    }

    /**
     * Log an error caught on the interactive menu path and, in debug mode,
     * rethrow it instead of masking it behind a generic message. Mirrors
     * UssdFramework::handle(): a swallowed error should never silently look
     * like "the menu didn't advance". Callers still return a user-facing
     * fallback for production (non-debug) sessions.
     */
    protected function reportInteractiveError(\Throwable $e, string $context): void
    {
        Log::error("USSD: {$context} failed", [
            'menu' => $this->title,
            'type' => $this->type,
            'error' => $e->getMessage(),
            'exception' => $e::class,
        ]);

        if ($this->config['debug'] ?? false) {
            throw $e;
        }
    }

    protected function calculateFormProgress(UssdSession $session): int
    {
        $totalFields = count($this->fields);
        $currentIndex = $session->getFormData('_form_field_index', 0);

        if ($totalFields === 0) {
            return 100;
        }

        return min(100, intval(($currentIndex / $totalFields) * 100));
    }

    protected function processPaginatedMenu(string $input, UssdSession $session): UssdResponse
    {
        $state = $session->getMenuData('pagination_state', 'browsing');

        return match ($state) {
            'browsing' => $this->handlePaginatedInput($input, $session),
            'searching' => $this->handleSearchInput($input, $session),
            default => $this->handlePaginatedInput($input, $session),
        };
    }

    protected function displayPaginatedMenu(UssdSession $session): UssdResponse
    {
        $session->setMenuData(['pagination_page' => 1, 'search_query' => '', 'pagination_state' => 'browsing']);

        return $this->showPaginatedContent($session);
    }

    protected function handlePaginatedInput(string $input, UssdSession $session): UssdResponse
    {
        $currentPage = $session->getMenuData('pagination_page', 1);

        if ($input === '00') {
            $session->setMenuData(['pagination_page' => $currentPage + 1]);

            return $this->showPaginatedContent($session);
        }

        if ($input === '98' && $this->type === 'searchable') {
            $session->setMenuData(['pagination_state' => 'searching']);

            return UssdResponse::continue('Enter search term:');
        }

        if (is_numeric($input) && $input >= 1 && $input <= $this->config['items_per_page']) {
            return $this->handleItemSelection($input, $session);
        }

        return $this->handleInvalidInput($input, $session);
    }

    protected function handleSearchInput(string $input, UssdSession $session): UssdResponse
    {
        if ($input === '' || $input === '0') {
            return UssdResponse::continue('Enter search term:');
        }

        $session->setMenuData(['search_query' => $input, 'pagination_page' => 1, 'pagination_state' => 'browsing']);

        return $this->showPaginatedContent($session);
    }

    protected function showPaginatedContent(UssdSession $session): UssdResponse
    {
        $data = $this->getProcessedData($session);

        // Snapshot the ordered dataset that is about to be displayed, so the
        // subsequent selection resolves the chosen index against exactly what
        // the user saw. Re-deriving from a non-deterministically ordered
        // provider at selection time could map the index to a different row.
        $session->setMenuData(['pagination_snapshot' => $data]);

        $currentPage = $session->getMenuData('pagination_page', 1);
        $itemsPerPage = $this->config['items_per_page'];
        $offset = ($currentPage - 1) * $itemsPerPage;

        $pagedData = array_slice($data, $offset, $itemsPerPage, true);
        $totalPages = ceil(count($data) / $itemsPerPage);

        if ($pagedData === []) {
            $searchQuery = $session->getMenuData('search_query', '');
            $message = empty($searchQuery) ? 'No items available.' : "No results found for '{$searchQuery}'.";
            $message = $this->addGlobalNavigation($message, $session);

            return UssdResponse::continue($message);
        }

        $message = $this->title."\n";

        $searchQuery = $session->getMenuData('search_query', '');
        if (! empty($searchQuery)) {
            $message .= "Search: '$searchQuery'\n";
        }

        $message .= "\n";

        $index = 1;
        foreach ($pagedData as $key => $item) {
            $displayText = $this->formatItem($key, $item, $currentPage);
            $message .= "{$index}. {$displayText}\n";
            $index++;
        }

        $message .= "\n";

        if ($totalPages > 1) {
            if ($currentPage < $totalPages) {
                $message .= "00. Next page\n";
            }
            $message .= "Page {$currentPage} of {$totalPages}\n";
        }

        if ($this->type === 'searchable') {
            $message .= "98. Search\n";
        }

        $message = $this->addGlobalNavigation($message, $session);

        return UssdResponse::continue($message);
    }

    protected function handleItemSelection(string $input, UssdSession $session): UssdResponse
    {
        // Resolve against the snapshot shown to the user (see showPaginatedContent);
        // fall back to a fresh fetch only if no snapshot is present.
        $data = $session->getMenuData('pagination_snapshot');
        if (! is_array($data)) {
            $data = $this->getProcessedData($session);
        }

        $currentPage = $session->getMenuData('pagination_page', 1);
        $itemsPerPage = $this->config['items_per_page'];
        $offset = ($currentPage - 1) * $itemsPerPage;

        $pagedData = array_slice($data, $offset, $itemsPerPage, true);
        $dataKeys = array_keys($pagedData);
        $inputIndex = (int) $input - 1;

        if (! isset($dataKeys[$inputIndex])) {
            return $this->handleInvalidInput($input, $session);
        }

        $selectedKey = $dataKeys[$inputIndex];
        $selectedItem = $pagedData[$selectedKey];

        if (isset($this->actions[$selectedKey]) && $this->framework) {
            $action = $this->actions[$selectedKey];
            if ($action instanceof ActionInterface) {
                return $action->execute($selectedKey, $session, $this->framework);
            }

            if (is_callable($action)) {
                return $action($selectedKey, $selectedItem, $session, $this->framework);
            }
        }

        $displayText = $this->formatItem($selectedKey, $selectedItem, $currentPage);

        return UssdResponse::end("You selected: {$displayText}");
    }

    protected function processConditionalMenu(string $input, UssdSession $session): UssdResponse
    {
        $selectedMenu = $this->getConditionalMenu($session);

        if ($selectedMenu instanceof UssdMenuInterface) {
            return $selectedMenu->process($input, $session);
        }

        return $this->handleInvalidInput($input, $session);
    }

    protected function displayConditionalMenu(UssdSession $session): UssdResponse
    {
        $selectedMenu = $this->getConditionalMenu($session);

        if ($selectedMenu instanceof UssdMenuInterface) {
            return $selectedMenu->display($session);
        }

        return UssdResponse::end('No suitable menu found.');
    }

    protected function getConditionalMenu(UssdSession $session): ?UssdMenuInterface
    {
        foreach ($this->conditions as $condition) {
            if (($condition['condition'])($session)) {
                $action = $condition['action'];
                if ($action instanceof UssdMenuInterface) {
                    return $action;
                }

                if (is_string($action) && $this->framework) {
                    return $this->framework->getMenu($action);
                }
            }
        }

        return null;
    }

    protected function processWizardMenu(string $input, UssdSession $session): UssdResponse
    {
        $currentStep = $session->getMenuData('wizard_step', 0);
        $steps = $this->config['wizard_steps'] ?? [];

        if (! isset($steps[$currentStep])) {
            return $this->completeWizard($session);
        }

        $step = $steps[$currentStep];

        $result = $this->processWizardStep($step, $input, $session);
        if ($result === true) {
            $session->setMenuData(['wizard_step' => $currentStep + 1]);

            return $this->displayWizardMenu($session);
        }

        if ($result instanceof UssdResponse) {
            return $result;
        }

        return $this->displayCurrentWizardStep($session);
    }

    protected function displayWizardMenu(UssdSession $session): UssdResponse
    {
        $session->getMenuData('wizard_step', 0);

        return $this->displayCurrentWizardStep($session);
    }

    protected function displayCurrentWizardStep(UssdSession $session): UssdResponse
    {
        $currentStep = $session->getMenuData('wizard_step', 0);
        $steps = $this->config['wizard_steps'] ?? [];

        if (! isset($steps[$currentStep])) {
            return $this->completeWizard($session);
        }

        $step = $steps[$currentStep];
        $message = $step['title'] ?? 'Step '.($currentStep + 1);

        if (isset($step['content'])) {
            $message .= "\n\n".$step['content'];
        }

        $message = $this->addGlobalNavigation($message, $session);

        return UssdResponse::continue($message);
    }

    protected function processWizardStep(array $step, string $input, UssdSession $session): mixed
    {
        if (isset($step['processor']) && is_callable($step['processor'])) {
            return ($step['processor'])($input, $session, $this->framework);
        }

        return $input !== '' && $input !== '0';
    }

    protected function completeWizard(UssdSession $session): UssdResponse
    {
        if ($this->onComplete && $this->framework) {
            return call_user_func($this->onComplete, $session, $this->framework);
        }

        return UssdResponse::end('Wizard completed successfully!');
    }

    protected function getProcessedData(UssdSession $session): array
    {
        $data = [];

        // Pass ($session, $filters) to match PaginatedMenu's data-provider
        // contract, so a provider written for either engine works in both.
        // Providers that declare only ($session) ignore the extra argument.
        $filters = [
            'search' => $session->getMenuData('search_query', ''),
            'page' => $session->getMenuData('pagination_page', 1),
        ];

        if (is_callable($this->dataProvider)) {
            $data = ($this->dataProvider)($session, $filters);
        } elseif (is_array($this->dataProvider)) {
            $data = $this->dataProvider;
        }

        $searchQuery = $session->getMenuData('search_query', '');
        if (! empty($searchQuery) && $this->type === 'searchable') {
            return $this->filterData($data, $searchQuery);
        }

        return $data;
    }

    /**
     * @param  array<int, string>|null  $searchFields  Columns to search; defaults to the
     *                                                 menu-level config. Field-driven callers
     *                                                 pass the field's own search fields so a
     *                                                 per-field `search_fields` is honored.
     */
    protected function filterData(array $data, string $query, ?array $searchFields = null): array
    {
        $query = strtolower(trim($query));
        $searchFields = $searchFields ?? ($this->config['search_fields'] ?? ['name']);
        $filtered = [];

        foreach ($data as $key => $item) {
            $searchText = '';

            if (is_array($item)) {
                foreach ($searchFields as $field) {
                    if (isset($item[$field])) {
                        $searchText .= ' '.strtolower((string) $item[$field]);
                    }
                }
            } else {
                $searchText = strtolower((string) $item);
            }

            if (str_contains($searchText, $query)) {
                $filtered[$key] = $item;
            }
        }

        return $filtered;
    }

    protected function formatItem(mixed $key, mixed $item, int $page = 1): string
    {
        if (is_callable($this->itemFormatter)) {
            // Pass ($key, $item, $page) to match PaginatedMenu's item_formatter
            // contract, so a formatter written for either paginated engine works
            // in both. Closures that declare fewer parameters ignore the extra.
            return ($this->itemFormatter)($key, $item, $page);
        }

        if (is_array($item)) {
            return $item['name'] ?? $item['title'] ?? $item['label'] ?? (string) $key;
        }

        return (string) $item;
    }

    protected function handleGlobalNavigation(string $input, UssdSession $session): ?UssdResponse
    {
        if (! $this->isGlobalNavigationEnabled()) {
            return null;
        }

        $navigation = $this->config['navigation'] ?? [];
        $navCommand = $this->findNavigationCommand($input);

        if ($navCommand === null) {
            return null;
        }

        return match ($navCommand) {
            $navigation['back'] ?? '99' => $this->handleBackNavigation($session),
            $navigation['home'] ?? '0' => $this->handleHomeNavigation($session),
            default => null,
        };
    }

    protected function findNavigationCommand(string $input): ?string
    {
        $navigation = $this->config['navigation'] ?? [];
        $navCommands = array_filter([
            $navigation['back'] ?? '99',
            $navigation['home'] ?? '0',
        ], static fn ($command): bool => $command !== '');

        if (in_array(trim($input), $navCommands)) {
            return trim($input);
        }

        if (str_contains($input, '*')) {
            $parts = explode('*', $input);
            foreach ($parts as $part) {
                $trimmed = trim($part);
                if (in_array($trimmed, $navCommands)) {
                    return $trimmed;
                }
            }
        }

        return null;
    }

    protected function handleBackNavigation(UssdSession $session): ?UssdResponse
    {
        if (in_array($this->type, ['form', 'enhanced_form', 'flexible_form'])) {
            return $this->handleFormBackNavigation($session);
        }

        if ($this->framework && $this->framework->goBack()) {
            $currentMenu = $this->framework->getCurrentMenuPublic();

            return $currentMenu->display($session);
        }

        return UssdResponse::continue('Cannot go back further.');
    }

    protected function handleFormBackNavigation(UssdSession $session): ?UssdResponse
    {
        $fieldIndex = $session->getFormData('_form_field_index', 0);

        if ($fieldIndex <= 0) {
            if ($this->framework && $this->framework->goBack()) {
                $currentMenu = $this->framework->getCurrentMenuPublic();

                return $currentMenu->display($session);
            }

            return UssdResponse::continue('Cannot go back further.');
        }

        $session->setFormData('_form_field_index', $fieldIndex - 1);
        $session->setFormData('_form_state', 'collecting');

        return $this->showCurrentField($session);
    }

    protected function handleHomeNavigation(UssdSession $session): ?UssdResponse
    {
        if ($this->framework instanceof UssdFramework) {
            $defaultMenu = $this->config['default_menu'] ?? 'main';
            $session->reset();
            $this->framework->navigateToMenu($defaultMenu);
            $homeMenu = $this->framework->getMenu($defaultMenu);

            return $homeMenu->display($session);
        }

        return UssdResponse::continue('Home navigation not available.');
    }

    protected function handleInvalidInput(?string $input, UssdSession $session): UssdResponse
    {
        $message = 'Invalid option. Please try again.';
        $message = $this->addGlobalNavigation($message, $session);

        return UssdResponse::continue($message);
    }

    protected function handlePaginatedField(FormField $field, string $input, UssdSession $session): UssdResponse
    {
        if ($input === '' || $input === '0') {
            return $this->showPaginatedFieldOptions($field, $session);
        }

        if ($input === '00') { // Next page
            $currentPage = $session->getFormData('_pagination_page', 1);
            $session->setFormData('_pagination_page', $currentPage + 1);

            return $this->showPaginatedFieldOptions($field, $session);
        }

        if ($input === '98' && $field->isSearchable()) {
            $session->setFormData('_form_state', 'searching');

            return UssdResponse::continue('Search '.$field->getName().":\nEnter search term:");
        }

        if (is_numeric($input) && (int) $input >= 1 && (int) $input <= $field->getItemsPerPage()) {
            return $this->handleFieldOptionSelection($field, $input, $session);
        }

        return $this->handleInvalidInput($input, $session);
    }

    /**
     * Session key holding the snapshot of the option set last displayed for a
     * given field, keyed per field so concurrent fields do not clobber each
     * other's snapshot.
     */
    private function fieldSnapshotKey(FormField $field): string
    {
        return '_options_snapshot_'.$field->getName();
    }

    protected function showPaginatedFieldOptions(FormField $field, UssdSession $session): UssdResponse
    {
        $options = $field->getOptions($session);

        // Ensure options is an array
        if (! is_array($options)) {
            $options = [];
        }

        $searchQuery = $session->getFormData('_search_query', '');

        if (! empty($searchQuery)) {
            $options = $this->filterData($options, $searchQuery, $field->getSearchFields());
        }

        // Snapshot the filtered, ordered option set that is about to be shown so
        // the selection resolves the chosen index against exactly this set, even
        // if the options provider is non-deterministically ordered.
        $session->setFormData($this->fieldSnapshotKey($field), $options);

        $currentPage = $session->getFormData('_pagination_page', 1);
        $itemsPerPage = $field->getItemsPerPage();
        $offset = ($currentPage - 1) * $itemsPerPage;

        $pagedOptions = array_slice($options, $offset, $itemsPerPage, true);
        $totalPages = ceil(count($options) / $itemsPerPage);

        if ($pagedOptions === []) {
            $message = empty($searchQuery) ? 'No options available.' : "No results found for '$searchQuery'.";
            $message = $this->addGlobalNavigation($message, $session);

            return UssdResponse::continue($message);
        }

        $message = $field->getPrompt()."\n";

        if (! empty($searchQuery)) {
            $message .= "Search: '{$searchQuery}'\n";
        }

        $message .= "\n";

        $index = 1;
        foreach ($pagedOptions as $key => $item) {
            $displayText = $this->formatItem($key, $item, $currentPage);
            $message .= "{$index}. {$displayText}\n";
            $index++;
        }

        $message .= "\n";

        if ($totalPages > 1) {
            if ($currentPage < $totalPages) {
                $message .= "00. Next page\n";
            }
            $message .= "Page {$currentPage} of {$totalPages}\n";
        }

        if ($field->isSearchable()) {
            $message .= "98. Search\n";
        }

        $message = $this->addGlobalNavigation($message, $session);

        return UssdResponse::continue($message);
    }

    protected function handleFieldOptionSelection(FormField $field, string $input, UssdSession $session): UssdResponse
    {
        // Resolve against the snapshot shown to the user (see
        // showPaginatedFieldOptions); fall back to a fresh, re-filtered fetch
        // only if no snapshot is present.
        $options = $session->getFormData($this->fieldSnapshotKey($field));

        if (! is_array($options)) {
            $options = $field->getOptions($session);

            if (! is_array($options)) {
                $options = [];
            }

            $searchQuery = $session->getFormData('_search_query', '');
            if (! empty($searchQuery)) {
                $options = $this->filterData($options, $searchQuery, $field->getSearchFields());
            }
        }

        $currentPage = $session->getFormData('_pagination_page', 1);
        $itemsPerPage = $field->getItemsPerPage();
        $offset = ($currentPage - 1) * $itemsPerPage;

        $pagedOptions = array_slice($options, $offset, $itemsPerPage, true);
        $optionKeys = array_keys($pagedOptions);
        $inputIndex = (int) $input - 1;

        if (! isset($optionKeys[$inputIndex])) {
            return $this->handleInvalidInput($input, $session);
        }

        $selectedKey = $optionKeys[$inputIndex];
        $selectedItem = $pagedOptions[$selectedKey];

        $valueToSave = is_array($selectedItem) ? ($selectedItem['id'] ?? $selectedKey) : $selectedKey;
        $session->setFormData($field->getName(), $valueToSave);

        if (is_array($selectedItem)) {
            $session->setFormData($field->getName().'_details', $selectedItem);
        }

        $session->setFormData('_form_state', 'collecting');
        $session->setFormData('_pagination_page', 1);
        $session->setFormData('_search_query', '');
        $session->setFormData($this->fieldSnapshotKey($field), null);

        return $this->moveToNextField($session);
    }

    protected function handleFormPaginationInput(string $input, UssdSession $session): UssdResponse
    {
        $fieldIndex = $session->getFormData('_form_field_index', 0);
        $fieldKeys = array_keys($this->fields);
        $field = $this->fields[$fieldKeys[$fieldIndex]];

        return $this->handlePaginatedField($field, $input, $session);
    }

    protected function handleFormSearchInput(string $input, UssdSession $session): UssdResponse
    {
        if ($input === '' || $input === '0') {
            return UssdResponse::continue('Enter search term:');
        }

        $session->setFormData('_search_query', $input);
        $session->setFormData('_form_state', 'paginating');
        $session->setFormData('_pagination_page', 1);

        $fieldIndex = $session->getFormData('_form_field_index', 0);
        $fieldKeys = array_keys($this->fields);
        $field = $this->fields[$fieldKeys[$fieldIndex]];

        return $this->showPaginatedFieldOptions($field, $session);
    }

    protected function addGlobalNavigation(string $message, UssdSession $session): string
    {
        if (! $this->isGlobalNavigationEnabled()) {
            return $message;
        }

        $navigation = $this->config['navigation'] ?? [];
        $navOptions = [];

        if ($this->shouldShowBack($session)) {
            $backCommand = $navigation['back'] ?? '99';
            $navOptions[] = "$backCommand. Back";
        }

        if ($this->shouldShowHome($session)) {
            $homeCommand = $navigation['home'] ?? '0';
            $navOptions[] = "$homeCommand. Main Menu";
        }

        if ($navOptions !== []) {
            $message .= "\n\n".implode("\n", $navOptions);
        }

        return $message;
    }

    protected function isGlobalNavigationEnabled(): bool
    {
        return $this->config['global_navigation']['enabled'] ?? true;
    }

    protected function shouldShowBack(UssdSession $session): bool
    {
        if ($session->canGoBack()) {
            return true;
        }

        return in_array($this->type, ['form', 'enhanced_form', 'flexible_form']) &&
            $session->getFormData('_form_field_index', 0) > 0;
    }

    protected function shouldShowHome(UssdSession $session): bool
    {
        $currentMenu = $session->getCurrentMenu();
        $defaultMenu = $this->config['default_menu'] ?? 'main';

        // No "Home" on the entry menu (current_menu is null there); it goes nowhere.
        return $currentMenu !== null && $currentMenu !== '' && $currentMenu !== $defaultMenu;
    }
}
