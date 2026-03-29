<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Services;

use Illuminate\Support\Facades\App;
use Moffhub\Ussd\UssdSession;

class TranslationService
{
    protected string $defaultLocale;

    /** @var array<int, string> */
    protected array $supportedLocales;

    public function __construct()
    {
        $this->defaultLocale = (string) config('ussd.localization.default_locale', config('app.locale', 'en'));
        $this->supportedLocales = (array) config('ussd.localization.supported_locales', ['en']);
    }

    /**
     * Translate a USSD string key.
     *
     * @param  array<string, string>  $replace
     */
    public function translate(string $key, array $replace = [], ?string $locale = null): string
    {
        $locale = $this->resolveLocale($locale);

        // Keys are stored in resources/lang/{locale}/ussd.php
        // Laravel namespaced translations use: namespace::file.key
        $fullKey = str_starts_with($key, 'ussd::') ? $key : 'ussd::ussd.'.$key;

        /** @var string $result */
        $result = trans($fullKey, $replace, $locale);

        // If trans() returns the key itself, it means no translation was found
        if ($result === $fullKey) {
            return $key;
        }

        return $result;
    }

    /**
     * Get the locale for a specific session.
     */
    public function getSessionLocale(UssdSession $session): string
    {
        $locale = $session->getUserPreference('locale');

        if (is_string($locale) && $this->isSupported($locale)) {
            return $locale;
        }

        return $this->defaultLocale;
    }

    /**
     * Set the locale for a specific session.
     */
    public function setSessionLocale(UssdSession $session, string $locale): bool
    {
        if (! $this->isSupported($locale)) {
            return false;
        }

        $session->setUserPreference('locale', $locale);

        return true;
    }

    /**
     * Check if a locale is supported.
     */
    public function isSupported(string $locale): bool
    {
        return in_array($locale, $this->supportedLocales, true);
    }

    /**
     * Get the list of supported locales.
     *
     * @return array<int, string>
     */
    public function getSupportedLocales(): array
    {
        return $this->supportedLocales;
    }

    /**
     * Get the default locale.
     */
    public function getDefaultLocale(): string
    {
        return $this->defaultLocale;
    }

    /**
     * Resolve a locale with fallback to default.
     */
    protected function resolveLocale(?string $locale): string
    {
        if ($locale !== null && $this->isSupported($locale)) {
            return $locale;
        }

        return $this->defaultLocale;
    }

    /**
     * Translate a USSD string using a session's locale.
     *
     * @param  array<string, string>  $replace
     */
    public function translateForSession(string $key, UssdSession $session, array $replace = []): string
    {
        $locale = $this->getSessionLocale($session);

        return $this->translate($key, $replace, $locale);
    }

    /**
     * Set the application locale temporarily for a session.
     */
    public function applySessionLocale(UssdSession $session): void
    {
        $locale = $this->getSessionLocale($session);
        App::setLocale($locale);
    }
}
