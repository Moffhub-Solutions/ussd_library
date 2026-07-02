<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Tests\Feature;

use InvalidArgumentException;
use Moffhub\Ussd\Actions\NavigateAction;
use Moffhub\Ussd\Builders\UssdBuilder;
use Moffhub\Ussd\Menus\SimpleMenu;
use Moffhub\Ussd\Tests\TestCase;

class MenuReferenceValidationTest extends TestCase
{
    private function config(): array
    {
        return [
            'default_menu' => 'main',
            'security' => ['rate_limiting' => false, 'input_sanitization' => false, 'audit_logging' => false],
            'analytics' => ['enabled' => false],
            'database' => ['enabled' => false],
        ];
    }

    public function test_build_throws_on_unregistered_navigation_target(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("menu 'balance' referenced by 'main' but not registered");

        UssdBuilder::create($this->config())
            ->customMenu('main', new SimpleMenu('Welcome', ['1' => 'Balance'], [
                '1' => new NavigateAction('balance'), // 'balance' is never registered
            ]))
            ->build();
    }

    public function test_build_passes_when_all_targets_are_registered(): void
    {
        $framework = UssdBuilder::create($this->config())
            ->customMenu('main', new SimpleMenu('Welcome', ['1' => 'Balance'], [
                '1' => new NavigateAction('balance'),
            ]))
            ->customMenu('balance', new SimpleMenu('Your balance is 100', []))
            ->build();

        $this->assertTrue($framework->hasMenu('balance'));
    }

    public function test_validation_can_be_disabled_via_config(): void
    {
        $config = $this->config();
        $config['validate_menu_references'] = false;

        $framework = UssdBuilder::create($config)
            ->customMenu('main', new SimpleMenu('Welcome', ['1' => 'Balance'], [
                '1' => new NavigateAction('nowhere'),
            ]))
            ->build();

        $this->assertTrue($framework->hasMenu('main'));
    }

    public function test_closure_navigation_is_not_flagged(): void
    {
        // Navigation inside a closure is opaque; build() must not choke on it.
        $framework = UssdBuilder::create($this->config())
            ->customMenu('main', new SimpleMenu('Welcome', ['1' => 'Go'], [
                '1' => fn ($input, $session, $fw) => $fw->navigateToMenuWithResponse('main'),
            ]))
            ->build();

        $this->assertTrue($framework->hasMenu('main'));
    }
}
