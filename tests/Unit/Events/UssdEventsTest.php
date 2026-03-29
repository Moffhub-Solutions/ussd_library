<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Tests\Unit\Events;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Moffhub\Ussd\Events\FormSubmitted;
use Moffhub\Ussd\Events\InputReceived;
use Moffhub\Ussd\Events\MenuEntered;
use Moffhub\Ussd\Events\MenuExited;
use Moffhub\Ussd\Events\NavigationPerformed;
use Moffhub\Ussd\Events\SessionEnded;
use Moffhub\Ussd\Events\SessionExpired;
use Moffhub\Ussd\Events\SessionResumed;
use Moffhub\Ussd\Events\SessionStarted;
use Moffhub\Ussd\Interfaces\UssdMenuInterface;
use Moffhub\Ussd\Tests\TestCase;
use Moffhub\Ussd\UssdFramework;
use Moffhub\Ussd\UssdResponse;
use Moffhub\Ussd\UssdSession;

class UssdEventsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
    }

    public function test_session_started_event_dispatched_for_new_session(): void
    {
        $framework = $this->createFrameworkWithMenu();
        $request = $this->createRequest();

        $framework->handle($request);

        Event::assertDispatched(SessionStarted::class, function (SessionStarted $event): bool {
            return $event->phone === '+254712345678'
                && $event->provider !== ''
                && $event->sessionId !== '';
        });
    }

    public function test_session_started_event_has_correct_properties(): void
    {
        $event = new SessionStarted('sess_123', '+254712345678', 'safaricom');

        $this->assertEquals('sess_123', $event->sessionId);
        $this->assertEquals('+254712345678', $event->phone);
        $this->assertEquals('safaricom', $event->provider);
    }

    public function test_session_resumed_event_has_correct_properties(): void
    {
        $event = new SessionResumed('sess_123', '+254712345678', true);

        $this->assertEquals('sess_123', $event->sessionId);
        $this->assertEquals('+254712345678', $event->phone);
        $this->assertTrue($event->wasRecovered);
    }

    public function test_session_ended_event_dispatched_on_end_response(): void
    {
        $menu = $this->createMock(UssdMenuInterface::class);
        $menu->method('process')->willReturn(new UssdResponse('Goodbye', UssdResponse::END));
        $menu->method('display')->willReturn(new UssdResponse('Welcome', UssdResponse::CONTINUE));
        $menu->method('setFramework');

        $framework = new UssdFramework([
            'default_menu' => 'main',
            'database' => ['enabled' => false],
            'security' => ['rate_limiting' => false, 'input_sanitization' => false, 'audit_logging' => false],
            'analytics' => ['enabled' => false],
            'cache' => ['enabled' => false],
        ]);
        $framework->registerMenu('main', $menu);

        $request = Request::create('/ussd', 'POST', [
            'phoneNumber' => '+254712345678',
            'text' => '1',
        ]);
        $request->merge(['sessionId' => 'test_session']);

        $framework->handle($request);

        Event::assertDispatched(SessionEnded::class, function (SessionEnded $event): bool {
            return $event->phone === '+254712345678'
                && $event->sessionId !== '';
        });
    }

    public function test_session_ended_event_has_correct_properties(): void
    {
        $event = new SessionEnded('sess_123', '+254712345678', 120.5, ['main', 'settings']);

        $this->assertEquals('sess_123', $event->sessionId);
        $this->assertEquals('+254712345678', $event->phone);
        $this->assertEquals(120.5, $event->duration);
        $this->assertEquals(['main', 'settings'], $event->menusVisited);
    }

    public function test_session_expired_event_has_correct_properties(): void
    {
        $event = new SessionExpired('sess_123', '+254712345678', 'settings');

        $this->assertEquals('sess_123', $event->sessionId);
        $this->assertEquals('+254712345678', $event->phone);
        $this->assertEquals('settings', $event->lastMenu);
    }

    public function test_menu_entered_event_has_correct_properties(): void
    {
        $event = new MenuEntered('sess_123', 'settings', 'main');

        $this->assertEquals('sess_123', $event->sessionId);
        $this->assertEquals('settings', $event->menuName);
        $this->assertEquals('main', $event->fromMenu);
    }

    public function test_menu_exited_event_has_correct_properties(): void
    {
        $event = new MenuExited('sess_123', 'main', 'settings', '2');

        $this->assertEquals('sess_123', $event->sessionId);
        $this->assertEquals('main', $event->menuName);
        $this->assertEquals('settings', $event->toMenu);
        $this->assertEquals('2', $event->selection);
    }

    public function test_menu_entered_dispatched_on_navigation(): void
    {
        $mainMenu = $this->createMock(UssdMenuInterface::class);
        $mainMenu->method('process')->willReturn(new UssdResponse('Welcome', UssdResponse::CONTINUE));
        $mainMenu->method('display')->willReturn(new UssdResponse('Welcome', UssdResponse::CONTINUE));
        $mainMenu->method('setFramework');

        $settingsMenu = $this->createMock(UssdMenuInterface::class);
        $settingsMenu->method('process')->willReturn(new UssdResponse('Settings', UssdResponse::CONTINUE));
        $settingsMenu->method('display')->willReturn(new UssdResponse('Settings', UssdResponse::CONTINUE));
        $settingsMenu->method('setFramework');

        $framework = new UssdFramework([
            'default_menu' => 'main',
            'database' => ['enabled' => false],
            'security' => ['rate_limiting' => false, 'input_sanitization' => false, 'audit_logging' => false],
            'analytics' => ['enabled' => false],
            'cache' => ['enabled' => false],
        ]);
        $framework->registerMenu('main', $mainMenu);
        $framework->registerMenu('settings', $settingsMenu);

        $request = Request::create('/ussd', 'POST', ['phoneNumber' => '+254712345678', 'text' => '']);
        $request->merge(['sessionId' => 'test_session']);
        $session = new UssdSession('+254712345678', 'test_session', []);
        $framework->setSession($session);
        $framework->setRequest($request);

        $framework->navigateToMenu('settings');

        Event::assertDispatched(MenuEntered::class, function (MenuEntered $event): bool {
            return $event->menuName === 'settings';
        });
    }

    public function test_input_received_event_dispatched(): void
    {
        $framework = $this->createFrameworkWithMenu();
        $request = $this->createRequest('1');

        $framework->handle($request);

        Event::assertDispatched(InputReceived::class, function (InputReceived $event): bool {
            return $event->rawInput === '1';
        });
    }

    public function test_input_received_event_has_correct_properties(): void
    {
        $event = new InputReceived('sess_123', 'main', '1');

        $this->assertEquals('sess_123', $event->sessionId);
        $this->assertEquals('main', $event->menuName);
        $this->assertEquals('1', $event->rawInput);
    }

    public function test_form_submitted_event_has_correct_properties(): void
    {
        $event = new FormSubmitted('sess_123', 'registration', ['name' => 'John']);

        $this->assertEquals('sess_123', $event->sessionId);
        $this->assertEquals('registration', $event->menuName);
        $this->assertEquals(['name' => 'John'], $event->formData);
    }

    public function test_navigation_performed_event_has_correct_properties(): void
    {
        $event = new NavigationPerformed('sess_123', 'back');

        $this->assertEquals('sess_123', $event->sessionId);
        $this->assertEquals('back', $event->action);
    }

    public function test_navigation_performed_dispatched_on_go_back(): void
    {
        $mainMenu = $this->createMock(UssdMenuInterface::class);
        $mainMenu->method('process')->willReturn(new UssdResponse('Welcome', UssdResponse::CONTINUE));
        $mainMenu->method('display')->willReturn(new UssdResponse('Welcome', UssdResponse::CONTINUE));
        $mainMenu->method('setFramework');

        $settingsMenu = $this->createMock(UssdMenuInterface::class);
        $settingsMenu->method('process')->willReturn(new UssdResponse('Settings', UssdResponse::CONTINUE));
        $settingsMenu->method('display')->willReturn(new UssdResponse('Settings', UssdResponse::CONTINUE));
        $settingsMenu->method('setFramework');

        $framework = new UssdFramework([
            'default_menu' => 'main',
            'database' => ['enabled' => false],
            'security' => ['rate_limiting' => false, 'input_sanitization' => false, 'audit_logging' => false],
            'analytics' => ['enabled' => false],
            'cache' => ['enabled' => false],
        ]);
        $framework->registerMenu('main', $mainMenu);
        $framework->registerMenu('settings', $settingsMenu);

        $request = Request::create('/ussd', 'POST', ['phoneNumber' => '+254712345678', 'text' => '']);
        $request->merge(['sessionId' => 'test_session']);
        $session = new UssdSession('+254712345678', 'test_session', []);
        $session->setCurrentMenu('main');
        $session->setCurrentMenu('settings');

        $framework->setSession($session);
        $framework->setRequest($request);

        $framework->goBack();

        Event::assertDispatched(NavigationPerformed::class, function (NavigationPerformed $event): bool {
            return $event->action === 'back';
        });
    }

    protected function createFrameworkWithMenu(): UssdFramework
    {
        $menu = $this->createMock(UssdMenuInterface::class);
        $menu->method('process')->willReturn(new UssdResponse('Welcome', UssdResponse::CONTINUE));
        $menu->method('display')->willReturn(new UssdResponse('Welcome', UssdResponse::CONTINUE));
        $menu->method('setFramework');

        $framework = new UssdFramework([
            'default_menu' => 'main',
            'database' => ['enabled' => false],
            'security' => ['rate_limiting' => false, 'input_sanitization' => false, 'audit_logging' => false],
            'analytics' => ['enabled' => false],
            'cache' => ['enabled' => false],
        ]);
        $framework->registerMenu('main', $menu);

        return $framework;
    }

    protected function createRequest(string $text = ''): Request
    {
        $request = Request::create('/ussd', 'POST', [
            'phoneNumber' => '+254712345678',
            'text' => $text,
        ]);
        $request->merge(['sessionId' => 'test_session_'.uniqid()]);

        return $request;
    }
}
