<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Tests\Feature;

use Moffhub\Ussd\Interfaces\MetricsRecorderInterface;
use Moffhub\Ussd\Menus\SimpleMenu;
use Moffhub\Ussd\Testing\UssdTester;
use Moffhub\Ussd\Tests\TestCase;
use Moffhub\Ussd\UssdResponse;

class MetricsRecorderTest extends TestCase
{
    public function test_bound_recorder_receives_funnel_metrics(): void
    {
        $recorder = new class implements MetricsRecorderInterface
        {
            /** @var array<int, string> */
            public array $entered = [];

            /** @var array<int, string> */
            public array $completed = [];

            /** @var array<int, string> */
            public array $dwell = [];

            public function menuEntered(string $menu): void
            {
                $this->entered[] = $menu;
            }

            public function menuCompleted(string $menu): void
            {
                $this->completed[] = $menu;
            }

            public function sessionAbandoned(string $menu): void {}

            public function dwell(string $menu, float $seconds): void
            {
                $this->dwell[] = $menu;
            }
        };

        // Bind before building the framework so initializeComponents resolves it.
        app()->instance(MetricsRecorderInterface::class, $recorder);

        $tester = UssdTester::fake(['default_menu' => 'main'])
            ->register('main', new SimpleMenu('Main', ['1' => 'Finish'], [
                '1' => fn (): UssdResponse => UssdResponse::end('Done'),
            ]));

        $tester->dial();     // enters 'main'
        $tester->send('1');  // enters + completes at 'main'

        $this->assertContains('main', $recorder->entered);
        $this->assertContains('main', $recorder->dwell);
        $this->assertSame(['main'], $recorder->completed);
    }

    public function test_default_recorder_is_a_noop(): void
    {
        // No binding: nothing should blow up and a normal flow works. assertSee
        // is a real assertion (throws on failure).
        UssdTester::fake(['default_menu' => 'main'])
            ->register('main', new SimpleMenu('Main', []))
            ->dial()
            ->assertSee('Main');
    }
}
