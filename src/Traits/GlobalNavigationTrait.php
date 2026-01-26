<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Traits;

use Exception;
use Moffhub\Ussd\UssdResponse;
use Moffhub\Ussd\UssdSession;

trait GlobalNavigationTrait
{
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
        if ($navString !== '' && $navString !== '0') {
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

        } catch (Exception) {
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

        } catch (Exception) {
            try {
                $defaultMenu = $this->config['default_menu'] ?? 'main';
                $homeMenu = $this->framework->getMenu($defaultMenu);

                return $homeMenu->display($session);
            } catch (Exception) {
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

        } catch (Exception) {
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

        } catch (Exception) {
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

        $this->config['global_navigation']['separator'] ?? ' | ';

        return implode("\n", $navOptions);
    }
}
