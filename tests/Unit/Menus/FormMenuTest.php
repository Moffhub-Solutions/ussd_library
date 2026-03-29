<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Tests\Unit\Menus;

use Illuminate\Http\Request;
use Moffhub\Ussd\Helpers\FormField;
use Moffhub\Ussd\Menus\FormMenu;
use Moffhub\Ussd\Tests\TestCase;
use Moffhub\Ussd\UssdFramework;
use Moffhub\Ussd\UssdResponse;
use Moffhub\Ussd\UssdSession;

class FormMenuTest extends TestCase
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

    // ==================== Initial display ====================

    public function test_show_initial_displays_title_and_first_field(): void
    {
        $menu = new FormMenu('Registration', [
            'name' => ['prompt' => 'Enter your name:'],
            'email' => ['prompt' => 'Enter your email:'],
        ]);
        $menu->setFramework($this->framework);
        $menu->setConfig($this->framework->getConfig());

        $response = $menu->process('', $this->session);

        $this->assertTrue($response->isContinue());
        $this->assertStringContainsString('Registration', $response->getMessage());
        $this->assertStringContainsString('Enter your name:', $response->getMessage());
    }

    public function test_no_fields_returns_end_response(): void
    {
        $menu = new FormMenu('Empty Form', []);
        $menu->setFramework($this->framework);
        $menu->setConfig($this->framework->getConfig());

        $response = $menu->process('', $this->session);

        $this->assertTrue($response->isEnd());
        $this->assertStringContainsString('No fields defined', $response->getMessage());
    }

    public function test_show_initial_sets_step_to_zero(): void
    {
        $menu = new FormMenu('Form', [
            'name' => ['prompt' => 'Name:'],
        ]);
        $menu->setFramework($this->framework);
        $menu->setConfig($this->framework->getConfig());

        $menu->process('', $this->session);

        $this->assertEquals(0, $this->session->getStep());
    }

    // ==================== processStep via reflection (unit test of internal logic) ====================

    public function test_process_step_stores_valid_input(): void
    {
        $menu = new FormMenu('Form', [
            'name' => ['prompt' => 'Name:'],
            'age' => ['prompt' => 'Age:'],
        ]);
        $menu->setFramework($this->framework);
        $menu->setConfig(array_merge($this->framework->getConfig(), [
            'navigation' => ['back' => '99', 'home' => '0'],
        ]));

        // Call processStep directly
        $reflection = new \ReflectionClass($menu);
        $method = $reflection->getMethod('processStep');
        $method->setAccessible(true);

        $this->session->setStep(0);
        $response = $method->invoke($menu, 'John', 0, $this->session);

        $this->assertEquals('John', $this->session->getFormData('name'));
        $this->assertTrue($response->isContinue());
        $this->assertStringContainsString('Age:', $response->getMessage());
    }

    public function test_process_step_validates_input(): void
    {
        $menu = new FormMenu('Form', [
            'email' => [
                'prompt' => 'Enter email:',
                'validator' => function ($input) {
                    if (! str_contains($input, '@')) {
                        return 'Invalid email address.';
                    }

                    return true;
                },
            ],
        ]);
        $menu->setFramework($this->framework);
        $menu->setConfig(array_merge($this->framework->getConfig(), [
            'navigation' => ['back' => '99', 'home' => '0'],
        ]));

        $reflection = new \ReflectionClass($menu);
        $method = $reflection->getMethod('processStep');
        $method->setAccessible(true);

        $this->session->setStep(0);
        $response = $method->invoke($menu, 'notanemail', 0, $this->session);

        $this->assertTrue($response->isContinue());
        $this->assertStringContainsString('Invalid email address', $response->getMessage());
        $this->assertStringContainsString('Enter email:', $response->getMessage());
    }

    public function test_process_step_accepts_valid_input(): void
    {
        $menu = new FormMenu('Form', [
            'email' => [
                'prompt' => 'Enter email:',
                'validator' => function ($input) {
                    if (! str_contains($input, '@')) {
                        return 'Invalid email address.';
                    }

                    return true;
                },
            ],
        ], fn () => UssdResponse::end('Done'));
        $menu->setFramework($this->framework);
        $menu->setConfig(array_merge($this->framework->getConfig(), [
            'navigation' => ['back' => '99', 'home' => '0'],
        ]));

        $reflection = new \ReflectionClass($menu);
        $method = $reflection->getMethod('processStep');
        $method->setAccessible(true);

        $this->session->setStep(0);
        $response = $method->invoke($menu, 'user@example.com', 0, $this->session);

        $this->assertTrue($response->isEnd());
        $this->assertStringContainsString('Done', $response->getMessage());
    }

    public function test_process_step_required_field_reprompts_on_empty(): void
    {
        $menu = new FormMenu('Form', [
            'first' => ['prompt' => 'First:'],
            'name' => ['prompt' => 'Enter name:'],
        ]);
        $menu->setFramework($this->framework);
        $menu->setConfig(array_merge($this->framework->getConfig(), [
            'navigation' => ['back' => '99', 'home' => '0'],
        ]));

        $reflection = new \ReflectionClass($menu);
        $method = $reflection->getMethod('processStep');
        $method->setAccessible(true);

        // Empty input on step 1 (second field, required)
        $this->session->setStep(1);
        $response = $method->invoke($menu, '', 1, $this->session);

        $this->assertTrue($response->isContinue());
        $this->assertStringContainsString('required', $response->getMessage());
    }

    public function test_process_step_optional_field_skips_on_empty(): void
    {
        $menu = new FormMenu('Form', [
            'first' => ['prompt' => 'First:'],
            'nickname' => ['prompt' => 'Nickname:', 'optional' => true],
        ], fn () => UssdResponse::end('Done'));
        $menu->setFramework($this->framework);
        $menu->setConfig(array_merge($this->framework->getConfig(), [
            'navigation' => ['back' => '99', 'home' => '0'],
        ]));

        $reflection = new \ReflectionClass($menu);
        $method = $reflection->getMethod('processStep');
        $method->setAccessible(true);

        $this->session->setStep(1);
        $response = $method->invoke($menu, '', 1, $this->session);

        // Should move to next (complete since it's the last field)
        $this->assertTrue($response->isEnd());
    }

    // ==================== Multi-step form via processStep ====================

    public function test_multi_step_form_collects_all_fields(): void
    {
        $completedData = null;
        $menu = new FormMenu('Form', [
            'name' => ['prompt' => 'Enter name:'],
            'age' => ['prompt' => 'Enter age:'],
        ], function ($formData, $session, $framework) use (&$completedData) {
            $completedData = $formData;

            return UssdResponse::end('Done');
        });
        $menu->setFramework($this->framework);
        $menu->setConfig(array_merge($this->framework->getConfig(), [
            'navigation' => ['back' => '99', 'home' => '0'],
        ]));

        $reflection = new \ReflectionClass($menu);
        $method = $reflection->getMethod('processStep');
        $method->setAccessible(true);

        // Step 0: enter name
        $this->session->setStep(0);
        $method->invoke($menu, 'John', 0, $this->session);
        $this->assertEquals('John', $this->session->getFormData('name'));

        // Step 1: enter age
        $response = $method->invoke($menu, '25', 1, $this->session);

        $this->assertTrue($response->isEnd());
        $this->assertNotNull($completedData);
        $this->assertEquals('John', $completedData['name']);
        $this->assertEquals('25', $completedData['age']);
    }

    // ==================== Form data persistence ====================

    public function test_form_data_persists_across_steps(): void
    {
        $menu = new FormMenu('Form', [
            'name' => ['prompt' => 'Name:'],
            'phone' => ['prompt' => 'Phone:'],
        ]);
        $menu->setFramework($this->framework);
        $menu->setConfig(array_merge($this->framework->getConfig(), [
            'navigation' => ['back' => '99', 'home' => '0'],
        ]));

        $reflection = new \ReflectionClass($menu);
        $method = $reflection->getMethod('processStep');
        $method->setAccessible(true);

        $this->session->setStep(0);
        $method->invoke($menu, 'John', 0, $this->session);

        $this->assertEquals('John', $this->session->getFormData('name'));
    }

    // ==================== Back navigation ====================

    public function test_back_navigation_goes_to_previous_field(): void
    {
        $menu = new FormMenu('Form', [
            'name' => ['prompt' => 'Name:'],
            'age' => ['prompt' => 'Age:'],
        ]);
        $menu->setFramework($this->framework);
        $menu->setConfig(array_merge($this->framework->getConfig(), [
            'navigation' => ['back' => '99', 'home' => '0'],
        ]));

        // Use handleBackNavigation directly
        $reflection = new \ReflectionClass($menu);
        $method = $reflection->getMethod('handleBackNavigation');
        $method->setAccessible(true);

        // We're at step 1 (age field), go back to step 0 (name)
        $this->session->setStep(1);
        $response = $method->invoke($menu, $this->session);

        $this->assertTrue($response->isContinue());
        $this->assertStringContainsString('Name:', $response->getMessage());
        $this->assertEquals(0, $this->session->getStep());
    }

    // ==================== onComplete callback ====================

    public function test_form_completes_with_default_message_when_no_callback(): void
    {
        $menu = new FormMenu('Form', [
            'name' => ['prompt' => 'Name:'],
        ]);
        $menu->setFramework($this->framework);
        $menu->setConfig(array_merge($this->framework->getConfig(), [
            'navigation' => ['back' => '99', 'home' => '0'],
        ]));

        $reflection = new \ReflectionClass($menu);
        $method = $reflection->getMethod('completeForm');
        $method->setAccessible(true);

        $response = $method->invoke($menu, $this->session);

        $this->assertTrue($response->isEnd());
        $this->assertStringContainsString('Form completed successfully', $response->getMessage());
    }

    // ==================== FormField objects ====================

    public function test_setfields_accepts_form_field_objects(): void
    {
        $field = new FormField('name', 'Enter your name:');
        $menu = new FormMenu('Form', ['name' => $field], fn () => UssdResponse::end('Done'));
        $menu->setFramework($this->framework);
        $menu->setConfig($this->framework->getConfig());

        $response = $menu->process('', $this->session);

        $this->assertTrue($response->isContinue());
        $this->assertStringContainsString('Enter your name:', $response->getMessage());
    }

    // ==================== moveToNextField ====================

    public function test_move_to_next_field_advances_step(): void
    {
        $menu = new FormMenu('Form', [
            'name' => ['prompt' => 'Name:'],
            'age' => ['prompt' => 'Age:'],
            'email' => ['prompt' => 'Email:'],
        ]);
        $menu->setFramework($this->framework);
        $menu->setConfig($this->framework->getConfig());

        $this->session->setStep(0);

        $reflection = new \ReflectionClass($menu);
        $method = $reflection->getMethod('moveToNextField');
        $method->setAccessible(true);

        $response = $method->invoke($menu, $this->session);

        $this->assertEquals(1, $this->session->getStep());
        $this->assertTrue($response->isContinue());
        $this->assertStringContainsString('Age:', $response->getMessage());
    }

    // ==================== Progress indicator ====================

    public function test_get_form_progress(): void
    {
        $menu = new FormMenu('Form', [
            'name' => ['prompt' => 'Name:'],
            'age' => ['prompt' => 'Age:'],
            'email' => ['prompt' => 'Email:'],
            'phone' => ['prompt' => 'Phone:'],
        ]);
        $menu->setFramework($this->framework);

        $reflection = new \ReflectionClass($menu);
        $method = $reflection->getMethod('getFormProgress');
        $method->setAccessible(true);

        $this->session->setStep(2);
        $progress = $method->invoke($menu, $this->session);

        $this->assertEquals(50, $progress);
    }
}
