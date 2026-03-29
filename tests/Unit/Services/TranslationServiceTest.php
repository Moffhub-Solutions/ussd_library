<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Tests\Unit\Services;

use Moffhub\Ussd\Services\TranslationService;
use Moffhub\Ussd\Tests\TestCase;
use Moffhub\Ussd\UssdSession;

class TranslationServiceTest extends TestCase
{
    private TranslationService $translationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->translationService = new TranslationService;
    }

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('ussd.localization.default_locale', 'en');
        $app['config']->set('ussd.localization.supported_locales', ['en', 'sw']);
    }

    public function test_translate_returns_translated_string(): void
    {
        $result = $this->translationService->translate('navigation.back');

        $this->assertEquals('Back', $result);
    }

    public function test_translate_returns_key_when_translation_missing(): void
    {
        $result = $this->translationService->translate('nonexistent.key');

        $this->assertEquals('nonexistent.key', $result);
    }

    public function test_translate_with_replacements(): void
    {
        $result = $this->translationService->translate('navigation.page_info', [
            'current' => '1',
            'total' => '5',
        ]);

        $this->assertEquals('Page 1 of 5', $result);
    }

    public function test_get_default_locale(): void
    {
        $this->assertEquals('en', $this->translationService->getDefaultLocale());
    }

    public function test_is_supported_returns_true_for_configured_locale(): void
    {
        $this->assertTrue($this->translationService->isSupported('en'));
        $this->assertTrue($this->translationService->isSupported('sw'));
    }

    public function test_is_supported_returns_false_for_unconfigured_locale(): void
    {
        $this->assertFalse($this->translationService->isSupported('fr'));
    }

    public function test_get_supported_locales(): void
    {
        $locales = $this->translationService->getSupportedLocales();

        $this->assertContains('en', $locales);
        $this->assertContains('sw', $locales);
    }

    public function test_set_session_locale(): void
    {
        $session = new UssdSession('+254712345678', 'test_session');

        $result = $this->translationService->setSessionLocale($session, 'sw');

        $this->assertTrue($result);
        $this->assertEquals('sw', $this->translationService->getSessionLocale($session));
    }

    public function test_set_session_locale_returns_false_for_unsupported(): void
    {
        $session = new UssdSession('+254712345678', 'test_session');

        $result = $this->translationService->setSessionLocale($session, 'fr');

        $this->assertFalse($result);
    }

    public function test_get_session_locale_returns_default_when_not_set(): void
    {
        $session = new UssdSession('+254712345678', 'test_session');

        $locale = $this->translationService->getSessionLocale($session);

        $this->assertEquals('en', $locale);
    }

    public function test_translate_for_session_uses_session_locale(): void
    {
        $session = new UssdSession('+254712345678', 'test_session');
        // Default locale should be used (en)
        $result = $this->translationService->translateForSession('navigation.back', $session);

        $this->assertEquals('Back', $result);
    }

    public function test_translate_errors(): void
    {
        $result = $this->translationService->translate('errors.invalid_option');

        $this->assertEquals('Invalid option. Please try again.', $result);
    }

    public function test_translate_validation_messages(): void
    {
        $result = $this->translationService->translate('validation.required');

        $this->assertEquals('This field is required.', $result);
    }

    public function test_translate_validation_with_parameters(): void
    {
        $result = $this->translationService->translate('validation.min_length', ['min' => '3']);

        $this->assertEquals('Input must be at least 3 characters.', $result);
    }

    public function test_translate_session_messages(): void
    {
        $result = $this->translationService->translate('session.welcome_back');

        $this->assertEquals('Welcome back! You can continue from where you left off.', $result);
    }
}
