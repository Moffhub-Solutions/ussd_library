<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Tests\Feature;

use Moffhub\Ussd\Menus\SimpleMenu;
use Moffhub\Ussd\Testing\UssdTester;
use Moffhub\Ussd\Tests\TestCase;

/**
 * The navigation keys as a caller meets them: Back and Home must be offered
 * exactly where they lead somewhere, and must actually go there.
 */
class NavigationKeysTest extends TestCase
{
    private function tester(): UssdTester
    {
        $welcome = new SimpleMenu('Welcome', [
            '1' => 'Services',
        ], [
            '1' => fn ($input, $session, $framework) => $framework->navigateToMenuWithResponse('services'),
        ]);

        $services = new SimpleMenu('Services', [
            '1' => 'Updates',
        ], [
            '1' => fn ($input, $session, $framework) => $framework->navigateToMenuWithResponse('updates'),
        ]);

        $updates = new SimpleMenu('Updates', ['1' => 'Latest'], []);

        return UssdTester::fake(['default_menu' => 'welcome'])
            ->register('welcome', $welcome)
            ->register('services', $services)
            ->register('updates', $updates);
    }

    public function test_entry_menu_offers_neither_back_nor_home(): void
    {
        // Both keys would be no-ops on the entry menu, so neither is advertised.
        $tester = $this->tester();

        $tester->dial();

        $this->assertStringContainsString('Welcome', $tester->message());
        $this->assertStringNotContainsString('99. Back', $tester->message());
        $this->assertStringNotContainsString('0. Main Menu', $tester->message());
    }

    /**
     * Regression: the entry menu recorded no history when the caller first
     * navigated away from it, so Back was hidden on every first-level screen.
     * Callers reached Services and were offered no way out.
     */
    public function test_first_level_menu_offers_back(): void
    {
        $tester = $this->tester();

        $tester->dial();
        $tester->send('1');

        $this->assertStringContainsString('Services', $tester->message());
        $this->assertStringContainsString('99. Back', $tester->message());
    }

    public function test_back_from_a_first_level_menu_returns_to_the_entry_menu(): void
    {
        $tester = $this->tester();

        $tester->dial();
        $tester->send('1');   // -> services
        $tester->send('99');  // back

        $this->assertStringContainsString('Welcome', $tester->message());
    }

    public function test_back_unwinds_one_level_at_a_time(): void
    {
        $tester = $this->tester();

        $tester->dial();
        $tester->send('1');   // -> services
        $tester->send('1');   // -> updates
        $tester->send('99');  // back -> services

        $this->assertStringContainsString('Services', $tester->message());

        $tester->send('99');  // back -> welcome

        $this->assertStringContainsString('Welcome', $tester->message());
    }

    public function test_home_returns_to_the_entry_menu_from_any_depth(): void
    {
        $tester = $this->tester();

        $tester->dial();
        $tester->send('1');   // -> services
        $tester->send('1');   // -> updates
        $tester->send('0');   // home

        $this->assertStringContainsString('Welcome', $tester->message());
    }

    public function test_back_on_the_entry_menu_redisplays_it(): void
    {
        // Nothing to go back to: re-render rather than end the session or
        // report an invalid option.
        $tester = $this->tester();

        $tester->dial();
        $tester->send('99');

        $this->assertTrue($tester->response()->isContinue());
        $this->assertStringContainsString('Welcome', $tester->message());
    }
}
