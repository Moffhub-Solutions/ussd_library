<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Tests\Unit\Builders;

use Moffhub\Ussd\Builders\MenuBuilder;
use Moffhub\Ussd\Menus\SimpleMenu;
use Moffhub\Ussd\Tests\TestCase;
use Moffhub\Ussd\UssdResponse;
use Moffhub\Ussd\UssdSession;

class MenuBuilderTest extends TestCase
{
    public function test_build_creates_simple_menu(): void
    {
        $builder = new MenuBuilder('test_menu');

        $menu = $builder
            ->title('Test Menu')
            ->option('1', 'First Option')
            ->option('2', 'Second Option')
            ->build();

        $this->assertInstanceOf(SimpleMenu::class, $menu);
    }

    public function test_title_sets_menu_title(): void
    {
        $session = new UssdSession('+254712345678', 'test');

        $menu = (new MenuBuilder('test'))
            ->title('My Custom Title')
            ->option('1', 'Option')
            ->build();

        $response = $menu->process('', $session);

        $this->assertStringContainsString('My Custom Title', $response->getMessage());
    }

    public function test_option_adds_options(): void
    {
        $session = new UssdSession('+254712345678', 'test');

        $menu = (new MenuBuilder('test'))
            ->title('Menu')
            ->option('1', 'Option One')
            ->option('2', 'Option Two')
            ->build();

        $response = $menu->process('', $session);

        $this->assertStringContainsString('1. Option One', $response->getMessage());
        $this->assertStringContainsString('2. Option Two', $response->getMessage());
    }

    public function test_option_with_action(): void
    {
        $session = new UssdSession('+254712345678', 'test');
        $actionCalled = false;

        $menu = (new MenuBuilder('test'))
            ->title('Menu')
            ->option('1', 'Action', function () use (&$actionCalled) {
                $actionCalled = true;

                return UssdResponse::end('Done');
            })
            ->build();

        $session->set('step', 0);
        $menu->process('1', $session);

        $this->assertTrue($actionCalled);
    }

    public function test_options_adds_multiple_options(): void
    {
        $session = new UssdSession('+254712345678', 'test');

        $menu = (new MenuBuilder('test'))
            ->title('Menu')
            ->options([
                '1' => 'First',
                '2' => 'Second',
                '3' => 'Third',
            ])
            ->build();

        $response = $menu->process('', $session);

        $this->assertStringContainsString('1. First', $response->getMessage());
        $this->assertStringContainsString('2. Second', $response->getMessage());
        $this->assertStringContainsString('3. Third', $response->getMessage());
    }

    public function test_action_adds_action_for_option(): void
    {
        $session = new UssdSession('+254712345678', 'test');
        $actionCalled = false;

        $menu = (new MenuBuilder('test'))
            ->title('Menu')
            ->option('1', 'Option')
            ->action('1', function () use (&$actionCalled) {
                $actionCalled = true;

                return UssdResponse::end('Done');
            })
            ->build();

        $session->set('step', 0);
        $menu->process('1', $session);

        $this->assertTrue($actionCalled);
    }

    public function test_on_complete_sets_callback(): void
    {
        $completeCalled = false;

        $menu = (new MenuBuilder('test'))
            ->title('Menu')
            ->option('1', 'Option')
            ->onComplete(function () use (&$completeCalled) {
                $completeCalled = true;

                return UssdResponse::end('Complete');
            })
            ->build();

        // The onComplete should be set on the menu
        $this->assertInstanceOf(SimpleMenu::class, $menu);
    }

    public function test_config_sets_configuration(): void
    {
        $menu = (new MenuBuilder('test'))
            ->title('Menu')
            ->option('1', 'Option')
            ->config(['custom_key' => 'custom_value'])
            ->build();

        $this->assertInstanceOf(SimpleMenu::class, $menu);
    }

    public function test_method_chaining(): void
    {
        $builder = new MenuBuilder('test');

        $result = $builder
            ->title('Title')
            ->option('1', 'One')
            ->option('2', 'Two')
            ->options(['3' => 'Three'])
            ->action('1', function () {
                return UssdResponse::end('Done');
            })
            ->config(['key' => 'value'])
            ->onComplete(function () {
                return UssdResponse::end('Done');
            });

        $this->assertInstanceOf(MenuBuilder::class, $result);

        $menu = $result->build();
        $this->assertInstanceOf(SimpleMenu::class, $menu);
    }
}
