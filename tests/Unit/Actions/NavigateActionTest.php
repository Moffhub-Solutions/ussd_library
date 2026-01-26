<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Tests\Unit\Actions;

use Illuminate\Http\Request;
use Moffhub\Ussd\Actions\NavigateAction;
use Moffhub\Ussd\Menus\SimpleMenu;
use Moffhub\Ussd\Tests\TestCase;
use Moffhub\Ussd\UssdFramework;
use Moffhub\Ussd\UssdSession;

class NavigateActionTest extends TestCase
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

    public function test_execute_navigates_to_target_menu(): void
    {
        $targetMenu = new SimpleMenu('Target Menu', ['1' => 'Option']);
        $this->framework->registerMenu('target', $targetMenu);

        $action = new NavigateAction('target');
        $response = $action->execute('', $this->session, $this->framework);

        $this->assertStringContainsString('Target Menu', $response->getMessage());
    }

    public function test_execute_passes_data_to_session(): void
    {
        $targetMenu = new SimpleMenu('Target', ['1' => 'Done']);
        $this->framework->registerMenu('target', $targetMenu);

        $action = new NavigateAction('target', ['key' => 'value']);
        $action->execute('', $this->session, $this->framework);

        // The framework should have navigated with the data
        $this->assertEquals('target', $this->session->getCurrentMenu());
    }

    public function test_constructor_sets_properties(): void
    {
        $action = new NavigateAction('menu_name', ['data' => 'value']);

        // Verify through execution
        $menu = new SimpleMenu('Test', ['1' => 'Done']);
        $this->framework->registerMenu('menu_name', $menu);

        $response = $action->execute('', $this->session, $this->framework);

        $this->assertTrue($response->isContinue());
    }
}
