<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Tests\Feature;

use Illuminate\Http\Request;
use Moffhub\Ussd\Menus\SimpleMenu;
use Moffhub\Ussd\Tests\TestCase;
use Moffhub\Ussd\UssdFramework;
use Moffhub\Ussd\UssdResponse;

class UssdFlowTest extends TestCase
{
    private UssdFramework $framework;

    protected function setUp(): void
    {
        parent::setUp();

        $this->framework = new UssdFramework([
            'default_menu' => 'main',
            'session_timeout' => 300,
            'security' => [
                'rate_limiting' => false,
                'input_sanitization' => true,
                'audit_logging' => false,
            ],
            'analytics' => ['enabled' => false],
            'database' => ['enabled' => false],
        ]);

        $this->setupMenus();
    }

    private function setupMenus(): void
    {
        // Main menu - callbacks use signature ($input, $session, $framework)
        $mainMenu = new SimpleMenu('Welcome to USSD App', [
            '1' => 'Check Balance',
            '2' => 'Send Money',
            '3' => 'Buy Airtime',
        ], [
            '1' => fn ($input, $session, $framework) => $framework->navigateToMenuWithResponse('balance'),
            '2' => fn ($input, $session, $framework) => $framework->navigateToMenuWithResponse('send_money'),
            '3' => fn ($input, $session, $framework) => $framework->navigateToMenuWithResponse('airtime'),
        ]);

        // Balance menu - use '9' for back since '0' is reserved for home navigation
        $balanceMenu = new SimpleMenu('Your balance is KES 1,500.00', [
            '9' => 'Back to Main Menu',
        ], [
            '9' => fn ($input, $session, $framework) => $framework->navigateToMenuWithResponse('main'),
        ]);

        // Send money menu
        $sendMoneyMenu = new SimpleMenu('Send Money', [
            '1' => 'To Mobile',
            '2' => 'To Bank',
            '9' => 'Back',
        ], [
            '9' => fn ($input, $session, $framework) => $framework->navigateToMenuWithResponse('main'),
        ]);

        // Airtime menu
        $airtimeMenu = new SimpleMenu('Buy Airtime', [
            '1' => 'For Self',
            '2' => 'For Others',
            '9' => 'Back',
        ], [
            '1' => fn ($input, $session, $framework) => UssdResponse::end('Airtime purchase successful. KES 100 added to your account.'),
            '9' => fn ($input, $session, $framework) => $framework->navigateToMenuWithResponse('main'),
        ]);

        $this->framework->registerMenu('main', $mainMenu);
        $this->framework->registerMenu('balance', $balanceMenu);
        $this->framework->registerMenu('send_money', $sendMoneyMenu);
        $this->framework->registerMenu('airtime', $airtimeMenu);
    }

    public function test_initial_request_shows_main_menu(): void
    {
        $request = $this->createUssdRequest('', '+254712345678', 'session1');

        $response = $this->framework->handle($request);

        $this->assertTrue($response->isContinue());
        $this->assertStringContainsString('Welcome to USSD App', $response->getMessage());
        $this->assertStringContainsString('1. Check Balance', $response->getMessage());
        $this->assertStringContainsString('2. Send Money', $response->getMessage());
        $this->assertStringContainsString('3. Buy Airtime', $response->getMessage());
    }

    public function test_selecting_balance_shows_balance(): void
    {
        // First request - show main menu
        $request1 = $this->createUssdRequest('', '+254712345678', 'session1');
        $this->framework->handle($request1);

        // Second request - select balance
        $request2 = $this->createUssdRequest('1', '+254712345678', 'session1');
        $response = $this->framework->handle($request2);

        $this->assertStringContainsString('KES 1,500.00', $response->getMessage());
    }

    public function test_complete_airtime_purchase_flow(): void
    {
        $phoneNumber = '+254712345678';
        $sessionId = 'session_airtime';

        // Step 1: Initial request
        $request1 = $this->createUssdRequest('', $phoneNumber, $sessionId);
        $response1 = $this->framework->handle($request1);
        $this->assertStringContainsString('Welcome', $response1->getMessage());

        // Step 2: Select Buy Airtime
        $request2 = $this->createUssdRequest('3', $phoneNumber, $sessionId);
        $response2 = $this->framework->handle($request2);
        $this->assertStringContainsString('Buy Airtime', $response2->getMessage());

        // Step 3: Select For Self
        $request3 = $this->createUssdRequest('1', $phoneNumber, $sessionId);
        $response3 = $this->framework->handle($request3);

        $this->assertTrue($response3->isEnd());
        $this->assertStringContainsString('successful', $response3->getMessage());
    }

    public function test_invalid_option_shows_error(): void
    {
        $request1 = $this->createUssdRequest('', '+254712345678', 'session1');
        $this->framework->handle($request1);

        $request2 = $this->createUssdRequest('9', '+254712345678', 'session1');
        $response = $this->framework->handle($request2);

        $this->assertStringContainsString('Invalid option', $response->getMessage());
        $this->assertTrue($response->isContinue());
    }

    public function test_navigation_back_to_main(): void
    {
        $phoneNumber = '+254712345678';
        $sessionId = 'session_back';

        // Go to balance
        $this->framework->handle($this->createUssdRequest('', $phoneNumber, $sessionId));
        $this->framework->handle($this->createUssdRequest('1', $phoneNumber, $sessionId));

        // Go back (using '9' since '0' is reserved for home navigation)
        $response = $this->framework->handle($this->createUssdRequest('9', $phoneNumber, $sessionId));

        $this->assertStringContainsString('Welcome to USSD App', $response->getMessage());
    }

    public function test_different_sessions_are_independent(): void
    {
        // User 1 starts
        $request1 = $this->createUssdRequest('', '+254712345678', 'session_user1');
        $response1 = $this->framework->handle($request1);

        // User 2 starts
        $request2 = $this->createUssdRequest('', '+254787654321', 'session_user2');
        $response2 = $this->framework->handle($request2);

        // Both should see main menu
        $this->assertStringContainsString('Welcome', $response1->getMessage());
        $this->assertStringContainsString('Welcome', $response2->getMessage());
    }

    public function test_response_format(): void
    {
        $request = $this->createUssdRequest('', '+254712345678', 'session1');
        $response = $this->framework->handle($request);

        $formatted = $response->formatForNetwork();

        $this->assertStringStartsWith('CON ', $formatted);
    }

    private function createUssdRequest(string $text, string $phoneNumber, string $sessionId): Request
    {
        return Request::create('/', 'POST', [
            'phoneNumber' => $phoneNumber,
            'text' => $text,
            'sessionId' => $sessionId,
            'serviceCode' => '*123#',
        ]);
    }
}
