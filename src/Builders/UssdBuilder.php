<?php

namespace Moffhub\Ussd\Builders;

use Moffhub\Ussd\UssdFramework;

class UssdBuilder
{
    protected UssdFramework $framework;
    protected array $menus = [];

    public function __construct(?UssdFramework $framework = null)
    {
        $this->framework = $framework ?: new UssdFramework;
    }

    /**
     * Create a new builder with configuration
     */
    public static function create(array $config = []): self
    {
        $framework = new UssdFramework($config);
        return new static($framework);
    }

    /**
     * Define a menu using a callback
     */
    public function menu(string $name, ?callable $callback = null): static
    {
        if ($callback) {
            $menuBuilder = new UnifiedMenuBuilder($name);
            $callback($menuBuilder);
            $this->menus[$name] = $menuBuilder->build();
        }

        return $this;
    }

    /**
     * Create a simple menu with options and actions
     */
    public function simpleMenu(string $name, string $title, array $options = [], array $actions = []): static
    {
        $this->menus[$name] = new SimpleMenu($title, $options, $actions);
        return $this;
    }

    /**
     * Create a form menu with fields and completion callback
     */
    public function formMenu(string $name, string $title, array $fields = [], ?callable $onComplete = null): static
    {
        $menu = new UssdMenu($title);
        $menu->setType('form');
        $menu->setFields($fields);
        $menu->setOnComplete($onComplete);

        $this->menus[$name] = $menu;
        return $this;
    }

    /**
     * Create an enhanced form menu with flexible field types and advanced features
     */
    public function enhancedFormMenu(string $name, string $title, array $fields = [], ?callable $onComplete = null): static
    {
        $menu = new UssdMenu($title);
        $menu->setType('enhanced_form');
        $menu->setFields($fields);
        $menu->setOnComplete($onComplete);
        $menu->enableFeatures(['pagination', 'search', 'validation', 'context_snapshots']);

        $this->menus[$name] = $menu;
        return $this;
    }

    /**
     * Create a flexible form with field builder
     */
    public function flexibleForm(string $name, string $title, ?callable $callback = null, ?callable $onComplete = null): static
    {
        $formBuilder = new FlexibleFormBuilder($title, $onComplete);

        if ($callback) {
            $callback($formBuilder);
        }

        $this->menus[$name] = $formBuilder->build();
        return $this;
    }

    /**
     * Create a paginated menu with data provider
     */
    public function paginatedMenu(string $name, string $title, mixed $dataProvider, array $options = []): static
    {
        $this->menus[$name] = new PaginatedMenu($title, $dataProvider, $options);
        return $this;
    }

    /**
     * Create a searchable paginated menu
     */
    public function searchableMenu(string $name, string $title, mixed $dataProvider, array $options = []): static
    {
        $options['searchable'] = true;
        $this->menus[$name] = new SearchablePaginatedMenu($title, $dataProvider, $options);
        return $this;
    }

    /**
     * Create a conditional menu with dynamic behavior
     */
    public function conditionalMenu(string $name, mixed $defaultMenu = null): ConditionalMenuBuilder
    {
        $menu = new ConditionalMenu($defaultMenu);
        $this->menus[$name] = $menu;
        return new ConditionalMenuBuilder($menu, $this);
    }

    /**
     * Create a wizard menu with multiple steps
     */
    public function wizardMenu(string $name, string $title, array $steps = [], ?callable $onComplete = null): static
    {
        $this->menus[$name] = new WizardMenu($title, $steps, $onComplete);
        return $this;
    }

    /**
     * Create a unified menu with all advanced features
     */
    public function unifiedMenu(string $name, string $title): UnifiedMenuBuilder
    {
        $menu = new UssdMenu($title);
        $this->menus[$name] = $menu;
        return new UnifiedMenuBuilder($menu, $this);
    }

    /**
     * Add a custom menu instance
     */
    public function customMenu(string $name, UssdMenuInterface $menu): static
    {
        $this->menus[$name] = $menu;
        return $this;
    }

    /**
     * Add a before process hook
     */
    public function onBeforeProcess(callable $callback): static
    {
        $this->framework->addHook('before_process', $callback);
        return $this;
    }

    /**
     * Add an after process hook
     */
    public function onAfterProcess(callable $callback): static
    {
        $this->framework->addHook('after_process', $callback);
        return $this;
    }

    /**
     * Add an error hook
     */
    public function onError(callable $callback): static
    {
        $this->framework->addHook('on_error', $callback);
        return $this;
    }

    /**
     * Add a session recovery hook
     */
    public function onSessionRecovery(callable $callback): static
    {
        $this->framework->addHook('session_recovery', $callback);
        return $this;
    }

    /**
     * Configure session management
     */
    public function configureSession(array $config): static
    {
        $currentConfig = $this->framework->getConfig();
        $mergedConfig = array_merge($currentConfig, $config);

        // Update framework configuration
        $reflection = new \ReflectionClass($this->framework);
        $configProperty = $reflection->getProperty('config');
        $configProperty->setAccessible(true);
        $configProperty->setValue($this->framework, $mergedConfig);

        return $this;
    }

    /**
     * Enable session continuation with recovery options
     */
    public function enableSessionContinuation(bool $enabled = true): static
    {
        return $this->configureSession([
            'enable_intelligent_recovery' => $enabled,
            'enable_context_preservation' => $enabled,
            'enable_session_migration' => $enabled,
        ]);
    }

    /**
     * Configure caching options
     */
    public function configureCaching(array $config): static
    {
        return $this->configureSession(['cache' => $config]);
    }

    /**
     * Configure security options
     */
    public function configureSecurity(array $config): static
    {
        return $this->configureSession(['security' => $config]);
    }

    /**
     * Configure analytics options
     */
    public function configureAnalytics(array $config): static
    {
        return $this->configureSession(['analytics' => $config]);
    }

    /**
     * Set global navigation options
     */
    public function configureNavigation(array $navigationConfig): static
    {
        return $this->configureSession(['navigation' => $navigationConfig]);
    }

    /**
     * Build and return the configured framework
     */
    public function build(): UssdFramework
    {
        $this->framework->registerMenus($this->menus);
        return $this->framework;
    }

    /**
     * Get the framework instance
     */
    public function getFramework(): UssdFramework
    {
        return $this->framework;
    }

    /**
     * Quick setup methods for common configurations
     */
    public function quickSetup(): static
    {
        return $this->enableSessionContinuation()
            ->configureCaching(['enabled' => true])
            ->configureSecurity(['rate_limiting' => true, 'input_sanitization' => true])
            ->configureAnalytics(['enabled' => true, 'track_user_journey' => true]);
    }

    /**
     * Setup for high-performance scenarios
     */
    public function highPerformanceSetup(): static
    {
        return $this->configureCaching([
            'enabled' => true,
            'menu_content_ttl' => 7200,
            'data_provider_ttl' => 1800,
        ])
            ->configureSession([
                'persistence_strategy' => 'cache',
                'cleanup_interval' => 1800,
            ]);
    }

    /**
     * Setup for development/testing
     */
    public function developmentSetup(): static
    {
        return $this->configureSession([
            'performance' => ['enable_profiling' => true, 'slow_query_threshold' => 500],
            'debug' => true,
        ])
            ->configureSecurity(['rate_limiting' => false]);
    }
}
