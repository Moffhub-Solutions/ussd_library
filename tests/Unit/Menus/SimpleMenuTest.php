<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Tests\Unit\Menus;

use Illuminate\Http\Request;
use Moffhub\Ussd\Actions\NavigateAction;
use Moffhub\Ussd\Menus\SimpleMenu;
use Moffhub\Ussd\Tests\TestCase;
use Moffhub\Ussd\UssdFramework;
use Moffhub\Ussd\UssdResponse;
use Moffhub\Ussd\UssdSession;

class SimpleMenuTest extends TestCase
{
    private UssdSession $session;

    private UssdFramework $framework;

    protected function setUp(): void
    {
        parent::setUp();
        $this->session = new UssdSession('+254712345678', 'test_session');
        $this->framework = new UssdFramework;
        $this->framework->setSession($this->session);
        $this->framework->setRequest(Request::create('/', 'POST', [
            'phoneNumber' => '+254712345678',
            'sessionId' => 'test_session',
        ]));
    }

    public function test_constructor_sets_properties(): void
    {
        $menu = new SimpleMenu('Main Menu', ['1' => 'Option 1'], []);

        $response = $menu->process('', $this->session);

        $this->assertStringContainsString('Main Menu', $response->getMessage());
        $this->assertStringContainsString('1. Option 1', $response->getMessage());
    }

    public function test_display_shows_title_and_options(): void
    {
        $menu = new SimpleMenu('Welcome', [
            '1' => 'Check Balance',
            '2' => 'Send Money',
            '3' => 'Buy Airtime',
        ]);

        $response = $menu->process('', $this->session);

        $this->assertTrue($response->isContinue());
        $this->assertStringContainsString('Welcome', $response->getMessage());
        $this->assertStringContainsString('1. Check Balance', $response->getMessage());
        $this->assertStringContainsString('2. Send Money', $response->getMessage());
        $this->assertStringContainsString('3. Buy Airtime', $response->getMessage());
    }

    public function test_display_shows_footer(): void
    {
        $menu = new SimpleMenu('Menu Title', ['1' => 'Option'], [], 'Press 0 to exit');

        $response = $menu->process('', $this->session);

        $this->assertStringContainsString('Press 0 to exit', $response->getMessage());
    }

    public function test_process_invalid_option_shows_error(): void
    {
        $menu = new SimpleMenu('Menu', ['1' => 'Valid']);
        $this->session->set('step', 0);

        $response = $menu->process('9', $this->session);

        $this->assertStringContainsString('Invalid option', $response->getMessage());
        $this->assertTrue($response->isContinue());
    }

    public function test_process_valid_option_without_action_ends_session(): void
    {
        $menu = new SimpleMenu('Menu', ['1' => 'Exit']);
        $menu->setFramework($this->framework);
        $this->session->set('step', 0);

        $response = $menu->process('1', $this->session);

        $this->assertTrue($response->isEnd());
    }

    public function test_process_valid_option_with_callable_action(): void
    {
        $actionCalled = false;

        $menu = new SimpleMenu('Menu', ['1' => 'Action'], [
            '1' => function ($input, $session, $framework) use (&$actionCalled): UssdResponse {
                $actionCalled = true;

                return UssdResponse::continue('Action executed');
            },
        ]);
        $menu->setFramework($this->framework);
        $this->session->set('step', 0);

        $response = $menu->process('1', $this->session);

        $this->assertTrue($actionCalled);
        $this->assertEquals('Action executed', $response->getMessage());
    }

    public function test_process_valid_option_with_action_interface(): void
    {
        $nextMenu = new SimpleMenu('Next Menu', ['1' => 'Done']);
        $this->framework->registerMenu('next_menu', $nextMenu);

        $menu = new SimpleMenu('Menu', ['1' => 'Navigate'], [
            '1' => new NavigateAction('next_menu'),
        ]);
        $menu->setFramework($this->framework);
        $this->session->set('step', 0);

        $response = $menu->process('1', $this->session);

        $this->assertStringContainsString('Next Menu', $response->getMessage());
    }

    public function test_add_option_adds_option_and_action(): void
    {
        $menu = new SimpleMenu('Menu');

        $menu->addOption('1', 'First Option', fn () => UssdResponse::end('Done'));

        $menu->addOption('2', 'Second Option');

        $response = $menu->process('', $this->session);

        $this->assertStringContainsString('1. First Option', $response->getMessage());
        $this->assertStringContainsString('2. Second Option', $response->getMessage());
    }

    public function test_set_footer_updates_footer(): void
    {
        $menu = new SimpleMenu('Menu', ['1' => 'Option']);
        $menu->setFooter('Custom Footer');

        $response = $menu->process('', $this->session);

        $this->assertStringContainsString('Custom Footer', $response->getMessage());
    }

    public function test_input_is_trimmed(): void
    {
        $actionCalled = false;

        $menu = new SimpleMenu('Menu', ['1' => 'Option'], [
            '1' => function ($input, $session, $framework) use (&$actionCalled): UssdResponse {
                $actionCalled = true;

                return UssdResponse::continue('Called');
            },
        ]);
        $menu->setFramework($this->framework);
        $this->session->set('step', 0);

        $menu->process('  1  ', $this->session);

        $this->assertTrue($actionCalled);
    }

    public function test_empty_input_shows_menu(): void
    {
        $menu = new SimpleMenu('Title', ['1' => 'Option']);

        $response = $menu->process('', $this->session);

        $this->assertStringContainsString('Title', $response->getMessage());
        $this->assertTrue($response->isContinue());
    }
}
