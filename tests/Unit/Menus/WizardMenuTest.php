<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Tests\Unit\Menus;

use Illuminate\Http\Request;
use Moffhub\Ussd\Menus\SimpleMenu;
use Moffhub\Ussd\Menus\WizardMenu;
use Moffhub\Ussd\Tests\TestCase;
use Moffhub\Ussd\UssdFramework;
use Moffhub\Ussd\UssdResponse;
use Moffhub\Ussd\UssdSession;

class WizardMenuTest extends TestCase
{
    private UssdSession $session;

    private UssdFramework $framework;

    protected function setUp(): void
    {
        parent::setUp();
        $this->session = new UssdSession('+254712345678', 'test_session');
        $this->framework = new UssdFramework([
            'navigation' => ['back' => '99', 'home' => '0', 'next' => '00', 'search' => '98'],
            'global_navigation' => ['enabled' => false],
            'default_menu' => 'main',
        ]);
        $this->framework->setSession($this->session);
        $this->framework->setRequest(Request::create('/', 'POST', [
            'phoneNumber' => '+254712345678',
            'sessionId' => 'test_session',
        ]));
    }

    // ==================== Step transitions ====================

    public function test_show_initial_displays_first_step(): void
    {
        $step1 = new SimpleMenu('Step 1: Enter Name', ['1' => 'Continue']);
        $wizard = new WizardMenu('Wizard', ['step1' => $step1]);
        $wizard->setFramework($this->framework);

        $response = $wizard->process('', $this->session);

        $this->assertTrue($response->isContinue());
        $this->assertStringContainsString('Step 1', $response->getMessage());
    }

    public function test_add_step_adds_step_to_wizard(): void
    {
        $wizard = new WizardMenu('Wizard');
        $step1 = new SimpleMenu('Step 1');
        $step2 = new SimpleMenu('Step 2');

        $wizard->addStep('first', $step1);
        $wizard->addStep('second', $step2);

        $wizard->setFramework($this->framework);

        $response = $wizard->process('', $this->session);

        $this->assertTrue($response->isContinue());
        $this->assertStringContainsString('Step 1', $response->getMessage());
    }

    // ==================== Wizard state persistence ====================

    public function test_wizard_tracks_completed_steps(): void
    {
        $wizard = new WizardMenu('Wizard', [
            'step1' => new SimpleMenu('Step 1', ['1' => 'Next']),
            'step2' => new SimpleMenu('Step 2', ['1' => 'Next']),
        ]);
        $wizard->setFramework($this->framework);

        // Show initial
        $wizard->process('', $this->session);

        // Menu data should be initialized
        $menuData = $this->session->getMenuData();
        $this->assertEquals(0, $menuData['current_step']);
        $this->assertEmpty($menuData['completed_steps']);
    }

    // ==================== onComplete callback ====================

    public function test_wizard_completes_with_default_message(): void
    {
        // Create a wizard with no steps to trigger completion
        $wizard = new WizardMenu('Wizard', []);
        $wizard->setFramework($this->framework);

        $response = $wizard->process('', $this->session);

        $this->assertTrue($response->isEnd());
        $this->assertStringContainsString('Wizard completed', $response->getMessage());
    }

    public function test_wizard_completes_with_custom_callback(): void
    {
        $callbackInvoked = false;
        $wizard = new WizardMenu('Wizard', [], function ($session, $framework) use (&$callbackInvoked) {
            $callbackInvoked = true;

            return UssdResponse::end('Custom completion!');
        });
        $wizard->setFramework($this->framework);

        // With no steps, it should complete immediately
        $response = $wizard->process('', $this->session);

        $this->assertTrue($callbackInvoked);
        $this->assertTrue($response->isEnd());
        $this->assertStringContainsString('Custom completion', $response->getMessage());
    }

    public function test_wizard_on_complete_can_return_string(): void
    {
        $wizard = new WizardMenu('Wizard', [], function ($session, $framework) {
            return 'String result';
        });
        $wizard->setFramework($this->framework);

        $response = $wizard->process('', $this->session);

        $this->assertTrue($response->isEnd());
        $this->assertStringContainsString('String result', $response->getMessage());
    }

    public function test_wizard_on_complete_without_framework_uses_default(): void
    {
        $wizard = new WizardMenu('Wizard', [], function ($session, $framework) {
            return UssdResponse::end('Done');
        });
        // Intentionally not setting framework

        $response = $wizard->process('', $this->session);

        $this->assertTrue($response->isEnd());
        $this->assertStringContainsString('Wizard completed', $response->getMessage());
    }
}
