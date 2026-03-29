<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Tests\Unit\Menus;

use Illuminate\Http\Request;
use Moffhub\Ussd\Actions\CallbackAction;
use Moffhub\Ussd\Actions\NavigateAction;
use Moffhub\Ussd\Builders\UssdBuilder;
use Moffhub\Ussd\Menus\ConditionalMenu;
use Moffhub\Ussd\Menus\SimpleMenu;
use Moffhub\Ussd\Tests\TestCase;
use Moffhub\Ussd\UssdFramework;
use Moffhub\Ussd\UssdResponse;
use Moffhub\Ussd\UssdSession;

class ConditionalMenuTest extends TestCase
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

    // ==================== Condition evaluation (true/false branches) ====================

    public function test_true_condition_selects_corresponding_menu(): void
    {
        $trueMenu = new SimpleMenu('True Menu', ['1' => 'Option']);
        $falseMenu = new SimpleMenu('False Menu', ['1' => 'Option']);

        $conditional = new ConditionalMenu($falseMenu);
        $conditional->addCondition(fn () => true, $trueMenu);
        $conditional->setFramework($this->framework);

        $response = $conditional->process('', $this->session);

        $this->assertTrue($response->isContinue());
        $this->assertStringContainsString('True Menu', $response->getMessage());
    }

    public function test_false_condition_falls_through_to_default(): void
    {
        $trueMenu = new SimpleMenu('True Menu', ['1' => 'Option']);
        $defaultMenu = new SimpleMenu('Default Menu', ['1' => 'Option']);

        $conditional = new ConditionalMenu($defaultMenu);
        $conditional->addCondition(fn () => false, $trueMenu);
        $conditional->setFramework($this->framework);

        $response = $conditional->process('', $this->session);

        $this->assertTrue($response->isContinue());
        $this->assertStringContainsString('Default Menu', $response->getMessage());
    }

    // ==================== Multiple conditions ====================

    public function test_first_true_condition_wins(): void
    {
        $menu1 = new SimpleMenu('Menu 1', ['1' => 'A']);
        $menu2 = new SimpleMenu('Menu 2', ['1' => 'B']);
        $menu3 = new SimpleMenu('Menu 3', ['1' => 'C']);

        $conditional = new ConditionalMenu;
        $conditional->addCondition(fn () => false, $menu1);
        $conditional->addCondition(fn () => true, $menu2);
        $conditional->addCondition(fn () => true, $menu3);
        $conditional->setFramework($this->framework);

        $response = $conditional->process('', $this->session);

        $this->assertStringContainsString('Menu 2', $response->getMessage());
    }

    public function test_all_conditions_false_uses_default(): void
    {
        $defaultMenu = new SimpleMenu('Default', ['1' => 'Default Option']);

        $conditional = new ConditionalMenu($defaultMenu);
        $conditional->addCondition(fn () => false, new SimpleMenu('A'));
        $conditional->addCondition(fn () => false, new SimpleMenu('B'));
        $conditional->setFramework($this->framework);

        $response = $conditional->process('', $this->session);

        $this->assertStringContainsString('Default', $response->getMessage());
    }

    // ==================== Default fallback menu ====================

    public function test_no_default_returns_end_response(): void
    {
        $conditional = new ConditionalMenu;
        $conditional->addCondition(fn () => false, new SimpleMenu('Nope'));
        $conditional->setFramework($this->framework);

        $response = $conditional->process('', $this->session);

        $this->assertTrue($response->isEnd());
        $this->assertStringContainsString('No menu available', $response->getMessage());
    }

    // ==================== Condition with session data ====================

    public function test_condition_receives_session_and_framework(): void
    {
        $receivedSession = null;
        $receivedFramework = null;

        $menu = new SimpleMenu('Session Menu', ['1' => 'Option']);

        $conditional = new ConditionalMenu;
        $conditional->addCondition(function ($session, $framework) use (&$receivedSession, &$receivedFramework) {
            $receivedSession = $session;
            $receivedFramework = $framework;

            return true;
        }, $menu);
        $conditional->setFramework($this->framework);

        $conditional->process('', $this->session);

        $this->assertSame($this->session, $receivedSession);
        $this->assertSame($this->framework, $receivedFramework);
    }

    public function test_condition_uses_session_data(): void
    {
        $this->session->setUserData('is_premium', true);

        $premiumMenu = new SimpleMenu('Premium', ['1' => 'Premium Feature']);
        $basicMenu = new SimpleMenu('Basic', ['1' => 'Basic Feature']);

        $conditional = new ConditionalMenu($basicMenu);
        $conditional->addCondition(function ($session) {
            return $session->getUserData('is_premium') === true;
        }, $premiumMenu);
        $conditional->setFramework($this->framework);

        $response = $conditional->process('', $this->session);

        $this->assertStringContainsString('Premium', $response->getMessage());
    }

    // ==================== Process step delegates to resolved menu ====================

    public function test_process_step_delegates_input_to_resolved_menu(): void
    {
        $menu = new SimpleMenu('Menu', ['1' => 'Option'], [
            '1' => fn ($input, $session, $framework) => UssdResponse::end('Selected!'),
        ]);
        $menu->setFramework($this->framework);

        $conditional = new ConditionalMenu;
        $conditional->addCondition(fn () => true, $menu);
        $conditional->setFramework($this->framework);

        // Use processStep directly via reflection
        $reflection = new \ReflectionClass($conditional);
        $method = $reflection->getMethod('processStep');
        $method->setAccessible(true);

        $response = $method->invoke($conditional, '1', 1, $this->session);

        $this->assertTrue($response->isEnd());
        $this->assertStringContainsString('Selected', $response->getMessage());
    }

    public function test_process_step_no_resolved_menu_returns_end(): void
    {
        $conditional = new ConditionalMenu;
        $conditional->addCondition(fn () => false, new SimpleMenu('Nope'));
        $conditional->setFramework($this->framework);

        $reflection = new \ReflectionClass($conditional);
        $method = $reflection->getMethod('processStep');
        $method->setAccessible(true);

        $response = $method->invoke($conditional, '1', 1, $this->session);

        $this->assertTrue($response->isEnd());
        $this->assertStringContainsString('Session ended', $response->getMessage());
    }

    // ==================== addCondition is fluent ====================

    public function test_add_condition_returns_self(): void
    {
        $conditional = new ConditionalMenu;
        $result = $conditional->addCondition(fn () => true, new SimpleMenu('M'));

        $this->assertSame($conditional, $result);
    }

    // ==================== Inline action + string-name branches (regression) ====================

    public function test_routes_to_inline_navigate_action(): void
    {
        $this->framework->registerMenu('paid', new SimpleMenu('Paid Account Menu', ['1' => 'Check']));

        $conditional = new ConditionalMenu;
        $conditional->addCondition(fn (): bool => true, new NavigateAction('paid'));
        $conditional->setFramework($this->framework);

        $response = $conditional->process('', $this->session);

        $this->assertStringContainsString('Paid Account Menu', $response->getMessage());
        $this->assertSame('paid', $this->session->getCurrentMenu());
    }

    public function test_routes_to_inline_callback_action(): void
    {
        $conditional = new ConditionalMenu;
        $conditional->addCondition(fn (): bool => true, new CallbackAction(fn (): UssdResponse => UssdResponse::end('Not registered.')));
        $conditional->setFramework($this->framework);

        $response = $conditional->process('', $this->session);

        $this->assertTrue($response->isEnd());
        $this->assertStringContainsString('Not registered.', $response->getMessage());
    }

    public function test_routes_to_registered_menu_name_string(): void
    {
        $this->framework->registerMenu('unpaid', new SimpleMenu('Unpaid Account Menu', ['1' => 'Activate']));

        $conditional = new ConditionalMenu;
        $conditional->addCondition(fn (): bool => true, 'unpaid');
        $conditional->setFramework($this->framework);

        $response = $conditional->process('', $this->session);

        $this->assertStringContainsString('Unpaid Account Menu', $response->getMessage());
    }

    public function test_default_branch_supports_inline_action(): void
    {
        $conditional = new ConditionalMenu;
        $conditional->addCondition(fn (): bool => false, new SimpleMenu('Nope'));
        $conditional->setDefaultMenu(new CallbackAction(fn (): UssdResponse => UssdResponse::end('Default branch.')));
        $conditional->setFramework($this->framework);

        $response = $conditional->process('', $this->session);

        $this->assertTrue($response->isEnd());
        $this->assertStringContainsString('Default branch.', $response->getMessage());
    }

    public function test_otherwise_accepts_inline_action_via_builder(): void
    {
        // Regression: otherwise() previously threw "Framework is not set" at build
        // time and only accepted strings; it must now accept an inline action.
        $builder = UssdBuilder::create();
        $builder->conditionalMenu('account')
            ->when(fn (): bool => false, new NavigateAction('paid'))
            ->otherwise(new CallbackAction(fn (): UssdResponse => UssdResponse::end('Default branch.')))
            ->build();

        $framework = $builder->build();
        $framework->setSession($this->session);

        $account = $framework->getMenu('account');
        $account->setFramework($framework);

        $response = $account->process('', $this->session);

        $this->assertTrue($response->isEnd());
        $this->assertStringContainsString('Default branch.', $response->getMessage());
    }
}
