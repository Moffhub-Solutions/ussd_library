<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Tests\Feature;

use Moffhub\Ussd\Menus\SimpleMenu;
use Moffhub\Ussd\Testing\UssdTester;
use Moffhub\Ussd\Tests\TestCase;
use Moffhub\Ussd\UssdResponse;

class UssdTesterTest extends TestCase
{
    private function tester(): UssdTester
    {
        $tester = UssdTester::fake(['default_menu' => 'main']);

        $tester->register('main', new SimpleMenu('Welcome to USSD App', [
            '1' => 'Check Balance',
            '3' => 'Buy Airtime',
        ], [
            '1' => fn ($input, $session, $framework) => $framework->navigateToMenuWithResponse('balance'),
            '3' => fn ($input, $session, $framework) => $framework->navigateToMenuWithResponse('airtime'),
        ]));

        $tester->register('balance', new SimpleMenu('Your balance is KES 1,500.00', []));

        $tester->register('airtime', new SimpleMenu('Buy Airtime', [
            '1' => 'For Self',
        ], [
            '1' => fn ($input, $session, $framework): UssdResponse => UssdResponse::end('Airtime purchase successful.'),
        ]));

        return $tester;
    }

    public function test_dial_shows_main_menu(): void
    {
        $this->tester()
            ->dial()
            ->assertContinue()
            ->assertSee('Welcome to USSD App')
            ->assertSee('1. Check Balance');
    }

    public function test_drive_walks_a_whole_session(): void
    {
        $this->tester()
            ->drive(['3', '1'])
            ->assertEnded()
            ->assertSee('successful');
    }

    public function test_selecting_balance_shows_balance(): void
    {
        $tester = $this->tester();

        $tester->dial();
        $tester->send('1')->assertSee('KES 1,500.00');
    }

    public function test_debug_mode_surfaces_menu_exceptions(): void
    {
        $tester = UssdTester::fake(['default_menu' => 'main']);

        $tester->register('main', new SimpleMenu('Menu', [
            '1' => 'Boom',
        ], [
            '1' => function (): void {
                throw new \RuntimeException('boom in handler');
            },
        ]));

        $tester->dial();

        // fake() sets debug => true, so the real cause is rethrown rather than
        // masked behind the generic "Service temporarily unavailable" message.
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('boom in handler');

        $tester->send('1');
    }
}
