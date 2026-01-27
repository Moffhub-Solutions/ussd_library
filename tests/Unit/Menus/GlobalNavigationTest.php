<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Tests\Unit\Menus;

use Illuminate\Http\Request;
use Moffhub\Ussd\Builders\FlexibleFormBuilder;
use Moffhub\Ussd\Menus\SimpleMenu;
use Moffhub\Ussd\Menus\UssdMenu;
use Moffhub\Ussd\Tests\TestCase;
use Moffhub\Ussd\UssdFramework;
use Moffhub\Ussd\UssdSession;

class GlobalNavigationTest extends TestCase
{
    private UssdSession $session;

    private UssdFramework $framework;

    protected function setUp(): void
    {
        parent::setUp();
        $this->session = new UssdSession('+254712345678', 'test_session');
        $this->framework = new UssdFramework([
            'global_navigation' => [
                'enabled' => true,
            ],
            'navigation' => [
                'home' => '0',
                'back' => '99',
            ],
            'default_menu' => 'main',
        ]);
        $this->framework->setSession($this->session);
        $this->framework->setRequest(Request::create('/', 'POST', [
            'phoneNumber' => '+254712345678',
            'sessionId' => 'test_session',
        ]));
    }

    public function test_home_navigation_from_simple_menu(): void
    {
        $mainMenu = new SimpleMenu('Main Menu', [
            '1' => 'Register',
            '2' => 'Check Status',
        ]);

        $subMenu = new SimpleMenu('Sub Menu', [
            '1' => 'Option A',
            '2' => 'Option B',
        ]);

        $this->framework->registerMenu('main', $mainMenu);
        $this->framework->registerMenu('sub', $subMenu);

        // Navigate to sub menu
        $this->session->setCurrentMenu('sub');
        $subMenu->setFramework($this->framework);

        // Press 0 to go home
        $response = $subMenu->process('0', $this->session);

        $this->assertTrue($response->isContinue());
        $this->assertStringContainsString('Main Menu', $response->getMessage());
    }

    public function test_home_navigation_from_form_at_step_zero(): void
    {
        $mainMenu = new SimpleMenu('Main Menu', [
            '1' => 'Register',
        ]);

        $formMenu = new UssdMenu('Registration Form');
        $formMenu->setType('form');
        $formMenu->setFields([
            'name' => ['type' => 'text', 'prompt' => 'Enter your name:'],
            'email' => ['type' => 'text', 'prompt' => 'Enter your email:'],
        ]);

        $this->framework->registerMenu('main', $mainMenu);
        $this->framework->registerMenu('register', $formMenu);

        // User is at registration form, step 0, already displayed form (form_field_index set)
        $this->session->setCurrentMenu('register');
        $this->session->setStep(0);
        $this->session->setFormData('_form_field_index', 0);
        $this->session->setFormData('_form_state', 'collecting');
        $formMenu->setFramework($this->framework);

        // Press 0 to go home - should navigate to main menu
        $response = $formMenu->process('0', $this->session);

        $this->assertTrue($response->isContinue());
        $this->assertStringContainsString('Main Menu', $response->getMessage());
    }

    public function test_home_navigation_from_form_at_step_greater_than_zero(): void
    {
        $mainMenu = new SimpleMenu('Main Menu', [
            '1' => 'Register',
        ]);

        $formMenu = new UssdMenu('Registration Form');
        $formMenu->setType('form');
        $formMenu->setFields([
            'name' => ['type' => 'text', 'prompt' => 'Enter your name:'],
            'email' => ['type' => 'text', 'prompt' => 'Enter your email:'],
        ]);

        $this->framework->registerMenu('main', $mainMenu);
        $this->framework->registerMenu('register', $formMenu);

        // User is at registration form, step 1 (already entered name)
        $this->session->setCurrentMenu('register');
        $this->session->setStep(1);
        $this->session->setFormData('_form_field_index', 1);
        $formMenu->setFramework($this->framework);

        // Press 0 to go home
        $response = $formMenu->process('0', $this->session);

        $this->assertTrue($response->isContinue());
        $this->assertStringContainsString('Main Menu', $response->getMessage());
    }

    public function test_home_navigation_from_flexible_form(): void
    {
        $mainMenu = new SimpleMenu('Main Menu', [
            '1' => 'Register',
        ]);

        $formBuilder = new FlexibleFormBuilder('Registration');
        $formBuilder->textField('surname', 'Enter your Surname:');
        $formBuilder->textField('othernames', 'Enter your Other Names:');
        $formMenu = $formBuilder->build();

        $this->framework->registerMenu('main', $mainMenu);
        $this->framework->registerMenu('register', $formMenu);

        // User is at registration form
        $this->session->setCurrentMenu('register');
        $this->session->setStep(0);
        $formMenu->setFramework($this->framework);

        // Press 0 to go home
        $response = $formMenu->process('0', $this->session);

        $this->assertTrue($response->isContinue());
        $this->assertStringContainsString('Main Menu', $response->getMessage());
    }

    public function test_back_navigation_in_form_goes_to_previous_field(): void
    {
        $formMenu = new UssdMenu('Registration Form');
        $formMenu->setType('form');
        $formMenu->setFields([
            'name' => ['type' => 'text', 'prompt' => 'Enter your name:'],
            'email' => ['type' => 'text', 'prompt' => 'Enter your email:'],
        ]);

        $this->framework->registerMenu('register', $formMenu);

        // User is at second field
        $this->session->setCurrentMenu('register');
        $this->session->setStep(0);
        $this->session->setFormData('_form_field_index', 1);
        $this->session->setFormData('_form_state', 'collecting');
        $this->session->setFormData('name', 'John');
        $formMenu->setFramework($this->framework);

        // Press 99 to go back
        $response = $formMenu->process('99', $this->session);

        $this->assertTrue($response->isContinue());
        // Should show the previous field (name)
        $this->assertStringContainsString('name', $response->getMessage());
    }

    public function test_global_navigation_disabled(): void
    {
        $frameworkNoNav = new UssdFramework([
            'global_navigation' => [
                'enabled' => false,
            ],
            'default_menu' => 'main',
        ]);
        $frameworkNoNav->setSession($this->session);
        $frameworkNoNav->setRequest(Request::create('/', 'POST', [
            'phoneNumber' => '+254712345678',
            'sessionId' => 'test_session',
        ]));

        $mainMenu = new SimpleMenu('Main Menu', ['1' => 'Option']);

        $formMenu = new UssdMenu('Form');
        $formMenu->setType('form');
        $formMenu->setFields([
            'name' => ['type' => 'text', 'prompt' => 'Enter your name:'],
        ]);

        $frameworkNoNav->registerMenu('main', $mainMenu);
        $frameworkNoNav->registerMenu('form', $formMenu);

        $this->session->setCurrentMenu('form');
        $this->session->setStep(0);
        $formMenu->setFramework($frameworkNoNav);

        // Press 0 - should show form initial since navigation is disabled
        $response = $formMenu->process('0', $this->session);

        // When nav is disabled, 0 should trigger showInitial for forms
        $this->assertStringContainsString('Enter your name', $response->getMessage());
    }

    public function test_custom_navigation_keys(): void
    {
        $frameworkCustomNav = new UssdFramework([
            'global_navigation' => [
                'enabled' => true,
            ],
            'navigation' => [
                'home' => '00',
                'back' => '98',
            ],
            'default_menu' => 'main',
        ]);
        $frameworkCustomNav->setSession($this->session);
        $frameworkCustomNav->setRequest(Request::create('/', 'POST', [
            'phoneNumber' => '+254712345678',
            'sessionId' => 'test_session',
        ]));

        $mainMenu = new SimpleMenu('Main Menu', ['1' => 'Register']);

        $formMenu = new UssdMenu('Form');
        $formMenu->setType('form');
        $formMenu->setFields([
            'name' => ['type' => 'text', 'prompt' => 'Enter your name:'],
        ]);

        $frameworkCustomNav->registerMenu('main', $mainMenu);
        $frameworkCustomNav->registerMenu('form', $formMenu);

        $this->session->setCurrentMenu('form');
        $this->session->setStep(0);
        $formMenu->setFramework($frameworkCustomNav);

        // Press 00 (custom home key) to go home
        $response = $formMenu->process('00', $this->session);

        $this->assertStringContainsString('Main Menu', $response->getMessage());
    }

    public function test_empty_input_shows_form_initial(): void
    {
        $formMenu = new UssdMenu('Registration Form');
        $formMenu->setType('form');
        $formMenu->setFields([
            'name' => ['type' => 'text', 'prompt' => 'Enter your name:'],
        ]);

        $this->framework->registerMenu('register', $formMenu);

        $this->session->setCurrentMenu('register');
        $this->session->setStep(0);
        $formMenu->setFramework($this->framework);

        // Empty input should show form, not navigate
        $response = $formMenu->process('', $this->session);

        $this->assertTrue($response->isContinue());
        $this->assertStringContainsString('Enter your name', $response->getMessage());
    }

    public function test_asterisk_input_with_navigation_command_is_detected(): void
    {
        $mainMenu = new SimpleMenu('Main Menu', [
            '1' => 'Register',
        ]);

        $subMenu = new SimpleMenu('Sub Menu', [
            '1' => 'Option A',
        ]);

        $this->framework->registerMenu('main', $mainMenu);
        $this->framework->registerMenu('sub', $subMenu);

        $this->session->setCurrentMenu('sub');
        $subMenu->setFramework($this->framework);

        // Input with asterisk containing 0 should be detected as home navigation
        // Note: This tests the findNavigationCommand logic which parses asterisk-separated inputs
        $response = $subMenu->process('1*0', $this->session);

        // The findNavigationCommand should find '0' in '1*0' and navigate home
        $this->assertStringContainsString('Main Menu', $response->getMessage());
    }
}
