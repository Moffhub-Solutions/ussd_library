<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Traits;

use Illuminate\Support\Facades\Log;
use Moffhub\Ussd\UssdResponse;
use Moffhub\Ussd\UssdSession;

trait GlobalNavigationTrait
{
    /**
     * Log a navigation error and, in debug mode, rethrow it rather than
     * masking it as a generic "navigation error" (which reads as "the menu
     * didn't advance"). Mirrors UssdFramework::handle()'s debug contract.
     */
    protected function reportNavigationError(\Throwable $e, string $context): void
    {
        Log::warning("USSD: {$context} failed", [
            'error' => $e->getMessage(),
            'exception' => $e::class,
        ]);

        if ($this->config['debug'] ?? false) {
            throw $e;
        }
    }

    protected function addGlobalNavigation(string $message, UssdSession $session): string
    {
        if (! $this->isGlobalNavigationEnabled()) {
            return $message;
        }

        $message = trim($message);
        if ($message === '' || $message === '0') {
            $message = 'Please make a selection:';
        }

        $navConfig = $this->config['global_navigation'] ?? [];
        $position = $navConfig['position'] ?? 'bottom';

        $navText = $this->buildNavigationText($session);

        if (empty($navText)) {
            return $message;
        }

        return match ($position) {
            'top' => $navText."\n\n".$message,
            'both' => $navText."\n\n".$message."\n\n".$navText,
            default => $message."\n\n".$navText, // bottom
        };
    }

    protected function buildNavigationText(UssdSession $session): string
    {
        $navConfig = $this->config['global_navigation'] ?? [];
        $navigation = $this->config['navigation'] ?? [];

        $navOptions = [];

        if ($this->shouldShowBack($session) && ($navConfig['show_back'] ?? true)) {
            $backKey = $navigation['back'] ?? '99';
            $backText = $navConfig['back_text'] ?? "{$backKey}. Back";
            $navOptions[] = $backText;
        }

        if ($this->shouldShowHome($session) && ($navConfig['show_home'] ?? true)) {
            $homeKey = $navigation['home'] ?? '0';
            $homeText = $navConfig['home_text'] ?? "{$homeKey}. Main Menu";
            $navOptions[] = $homeText;
        }

        if ($navOptions === []) {
            return '';
        }

        $separator = $navConfig['separator'] ?? ' | ';
        $navString = implode($separator, $navOptions);
        if ($navString !== '0') {
            return "---\n".$navString;
        }

        return $navString;
    }

    protected function isGlobalNavigationEnabled(): bool
    {
        return $this->config['global_navigation']['enabled'] ?? true;
    }

    protected function shouldShowBack(UssdSession $session): bool
    {
        $currentMenu = $session->getCurrentMenu();
        $defaultMenu = $this->config['default_menu'] ?? 'main';

        if ($currentMenu === $defaultMenu) {
            return false;
        }
        if ($session->canGoBack()) {
            return true;
        }

        return $session->getStep() > 0;
    }

    protected function shouldShowHome(UssdSession $session): bool
    {
        $currentMenu = $session->getCurrentMenu();
        $defaultMenu = $this->config['default_menu'] ?? 'main';

        return $currentMenu !== $defaultMenu;
    }

    protected function processGlobalNavigation(string $input, UssdSession $session): ?UssdResponse
    {
        if (! $this->isGlobalNavigationEnabled()) {
            return null;
        }

        $navigation = $this->config['navigation'] ?? [];

        if ($input === ($navigation['back'] ?? '99') && $this->shouldShowBack($session)) {
            return $this->handleBackNavigation($session);
        }

        if ($input === ($navigation['home'] ?? '0') && $this->shouldShowHome($session)) {
            return $this->handleHomeNavigation($session);
        }

        return null;
    }

    protected function handleBackNavigation(UssdSession $session): UssdResponse
    {
        try {
            if ($this->goBack()) {
                $currentMenu = $this->framework->getCurrentMenuPublic();

                return $currentMenu->display($session);
            }

            $currentStep = $session->getStep();
            if ($currentStep > 0) {
                $session->setStep($currentStep - 1);
                $currentMenu = $this->framework->getCurrentMenuPublic();

                return $currentMenu->display($session);
            }

            $currentMenu = $this->framework->getCurrentMenuPublic();

            return $currentMenu->display($session);

        } catch (\Throwable $e) {
            $this->reportNavigationError($e, 'back navigation');

            return UssdResponse::continue('Navigation error. Please try again.');
        }
    }

    protected function handleHomeNavigation(UssdSession $session): UssdResponse
    {
        try {
            $defaultMenu = $this->config['default_menu'] ?? 'main';

            $session->reset();

            $this->navigateTo($defaultMenu);

            $homeMenu = $this->framework->getMenu($defaultMenu);

            return $homeMenu->display($session);

        } catch (\Throwable $e) {
            $this->reportNavigationError($e, 'home navigation');

            try {
                $defaultMenu = $this->config['default_menu'] ?? 'main';
                $homeMenu = $this->framework->getMenu($defaultMenu);

                return $homeMenu->display($session);
            } catch (\Throwable $inner) {
                $this->reportNavigationError($inner, 'home navigation fallback');

                return UssdResponse::end('Service error. Please try again.');
            }
        }
    }

    protected function navigateTo(string $menuName, array $data = []): bool
    {
        try {
            if (! isset($this->framework)) {
                return false;
            }

            $this->framework->navigateToMenu($menuName, $data);

            return true;

        } catch (\Throwable $e) {
            $this->reportNavigationError($e, "navigateTo({$menuName})");

            return false;
        }
    }

    protected function goBack(): bool
    {
        try {
            if (! isset($this->framework)) {
                return false;
            }

            return $this->framework->goBack();

        } catch (\Throwable $e) {
            $this->reportNavigationError($e, 'goBack');

            return false;
        }
    }

    protected function shouldShowNavigationSeparator(): bool
    {
        $navConfig = $this->config['global_navigation'] ?? [];

        return $navConfig['show_separator'] ?? true;
    }

    protected function getNavigationSeparator(): string
    {
        $navConfig = $this->config['global_navigation'] ?? [];

        return $navConfig['separator_text'] ?? '---';
    }

    protected function buildContextualNavigation(UssdSession $session, array $additionalOptions = []): string
    {
        $navOptions = [];

        foreach ($additionalOptions as $key => $text) {
            $navOptions[] = "{$key}. {$text}";
        }

        $globalNav = $this->buildNavigationText($session);
        if (! empty($globalNav)) {
            if ($navOptions !== []) {
                $navOptions[] = $globalNav;
            } else {
                return $globalNav;
            }
        }

        if ($navOptions === []) {
            return '';
        }

        return implode("\n", $navOptions);
    }
}
