<?php

namespace Moffhub\Ussd\Builders;

use Closure;
use Moffhub\Ussd\Interfaces\UssdMenuInterface;
use Moffhub\Ussd\Menus\ConditionalMenu;
use Moffhub\Ussd\Menus\PaginatedMenu;
use Moffhub\Ussd\Menus\SearchablePaginatedMenu;
use Moffhub\Ussd\Menus\SimpleMenu;
use Moffhub\Ussd\Menus\UssdMenu;
use Moffhub\Ussd\Menus\WizardMenu;
use Moffhub\Ussd\UssdFramework;

class UssdBuilder
{
    protected UssdFramework $framework;

    /**
     * @var array<string, UssdMenu>
     */
    protected array $menus = [];

    public function __construct(?UssdFramework $framework = null)
    {
        $this->framework = $framework ?: new UssdFramework;
    }

    public static function create(array $config = []): self
    {
        $framework = new UssdFramework($config);

        return new static($framework);
    }

    public function menu(string $name, ?Closure $callback = null): static
    {
        if ($callback) {
            $ussdMenu = new UssdMenu($name);
            $menuBuilder = new UnifiedMenuBuilder($ussdMenu, $this);
            $callback($menuBuilder);
            $this->menus[$name] = $menuBuilder->build();
        }

        return $this;
    }

    public function simpleMenu(string $name, string $title, array $options = [], array $actions = []): static
    {
        $this->menus[$name] = new SimpleMenu($title, $options, $actions);

        return $this;
    }

    public function formMenu(string $name, string $title, array $fields = [], ?callable $onComplete = null): static
    {
        $menu = new UssdMenu($title);
        $menu->setType('form');
        $menu->setFields($fields);
        $menu->setOnComplete($onComplete);

        $this->menus[$name] = $menu;

        return $this;
    }

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

    public function flexibleForm(string $name, string $title, ?callable $callback = null, ?callable $onComplete = null): static
    {
        $formBuilder = new FlexibleFormBuilder($title, $onComplete);

        if ($callback) {
            $callback($formBuilder);
        }

        $this->menus[$name] = $formBuilder->build();

        return $this;
    }

    public function paginatedMenu(string $name, string $title, mixed $dataProvider, array $options = []): static
    {
        $this->menus[$name] = new PaginatedMenu($title, $dataProvider, $options);

        return $this;
    }

    public function searchableMenu(string $name, string $title, mixed $dataProvider, array $options = []): static
    {
        $options['searchable'] = true;
        $this->menus[$name] = new SearchablePaginatedMenu($title, $dataProvider, $options);

        return $this;
    }

    public function conditionalMenu(string $name, mixed $defaultMenu = null): ConditionalMenuBuilder
    {
        $menu = new ConditionalMenu($defaultMenu);
        $this->menus[$name] = $menu;

        return new ConditionalMenuBuilder($menu, $this);
    }

    public function wizardMenu(string $name, string $title, array $steps = [], ?callable $onComplete = null): static
    {
        $this->menus[$name] = new WizardMenu($title, $steps, $onComplete);

        return $this;
    }

    public function unifiedMenu(string $name, string $title): UnifiedMenuBuilder
    {
        $menu = new UssdMenu($title);
        $this->menus[$name] = $menu;

        return new UnifiedMenuBuilder($menu, $this);
    }

    public function customMenu(string $name, UssdMenuInterface $menu): static
    {
        $this->menus[$name] = $menu;

        return $this;
    }

    public function onBeforeProcess(callable $callback): static
    {
        $this->framework->addHook('before_process', $callback);

        return $this;
    }

    public function onAfterProcess(callable $callback): static
    {
        $this->framework->addHook('after_process', $callback);

        return $this;
    }

    public function onError(callable $callback): static
    {
        $this->framework->addHook('on_error', $callback);

        return $this;
    }

    public function onSessionRecovery(callable $callback): static
    {
        $this->framework->addHook('session_recovery', $callback);

        return $this;
    }

    public function configureSession(array $config): static
    {
        $currentConfig = $this->framework->getConfig();
        $mergedConfig = array_merge($currentConfig, $config);

        $reflection = new \ReflectionClass($this->framework);
        $configProperty = $reflection->getProperty('config');
        $configProperty->setAccessible(true);
        $configProperty->setValue($this->framework, $mergedConfig);

        return $this;
    }

    public function enableSessionContinuation(bool $enabled = true): static
    {
        return $this->configureSession([
            'enable_intelligent_recovery' => $enabled,
            'enable_context_preservation' => $enabled,
            'enable_session_migration' => $enabled,
        ]);
    }

    public function configureCaching(array $config): static
    {
        return $this->configureSession(['cache' => $config]);
    }

    public function configureSecurity(array $config): static
    {
        return $this->configureSession(['security' => $config]);
    }

    public function configureAnalytics(array $config): static
    {
        return $this->configureSession(['analytics' => $config]);
    }

    public function configureNavigation(array $navigationConfig): static
    {
        return $this->configureSession(['navigation' => $navigationConfig]);
    }

    public function build(): UssdFramework
    {
        $this->framework->registerMenus($this->menus);

        return $this->framework;
    }

    public function getFramework(): UssdFramework
    {
        return $this->framework;
    }

    public function quickSetup(): static
    {
        return $this->enableSessionContinuation()
            ->configureCaching(['enabled' => true])
            ->configureSecurity(['rate_limiting' => true, 'input_sanitization' => true])
            ->configureAnalytics(['enabled' => true, 'track_user_journey' => true]);
    }

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

    public function developmentSetup(): static
    {
        return $this->configureSession([
            'performance' => ['enable_profiling' => true, 'slow_query_threshold' => 500],
            'debug' => true,
        ])
            ->configureSecurity(['rate_limiting' => false]);
    }
}
