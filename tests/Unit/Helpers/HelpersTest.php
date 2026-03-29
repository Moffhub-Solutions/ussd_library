<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Tests\Unit\Helpers;

use Moffhub\Ussd\Tests\TestCase;

class HelpersTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('ussd.localization.default_locale', 'en');
        $app['config']->set('ussd.localization.supported_locales', ['en']);
    }

    public function test_ussd_helper_function_exists(): void
    {
        $this->assertTrue(function_exists('__ussd'));
    }

    public function test_ussd_helper_translates_key(): void
    {
        $result = __ussd('navigation.back');

        $this->assertEquals('Back', $result);
    }

    public function test_ussd_helper_with_parameters(): void
    {
        $result = __ussd('navigation.page_info', ['current' => '2', 'total' => '10']);

        $this->assertEquals('Page 2 of 10', $result);
    }

    public function test_ussd_helper_returns_key_for_missing_translation(): void
    {
        $result = __ussd('missing.key.here');

        $this->assertEquals('missing.key.here', $result);
    }
}
