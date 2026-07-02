<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Tests\Feature;

use Moffhub\Ussd\Menus\SimpleMenu;
use Moffhub\Ussd\Testing\UssdTester;
use Moffhub\Ussd\Tests\TestCase;
use Moffhub\Ussd\UssdResponse;

class RequestDeduplicationTest extends TestCase
{
    /**
     * A menu whose option '1' has a side effect: it increments $calls by ref.
     */
    private function payMenu(int &$calls): SimpleMenu
    {
        return new SimpleMenu('Pay 100?', ['1' => 'Confirm'], [
            '1' => function () use (&$calls): UssdResponse {
                $calls++;

                return UssdResponse::end('Payment sent.');
            },
        ]);
    }

    public function test_retried_step_is_processed_once(): void
    {
        $calls = 0;

        $tester = UssdTester::fake(['default_menu' => 'main'])
            ->register('main', $this->payMenu($calls));

        $tester->dial();
        $tester->send('1');            // real request: side effect runs
        $first = $tester->message();
        $tester->send('1');            // gateway retry: served from cache
        $second = $tester->message();

        $this->assertSame(1, $calls, 'Side effect must run exactly once for a retried request.');
        $this->assertSame($first, $second);
    }

    public function test_dedupe_can_be_disabled(): void
    {
        $calls = 0;

        $tester = UssdTester::fake([
            'default_menu' => 'main',
            'deduplication' => ['enabled' => false],
        ])->register('main', $this->payMenu($calls));

        $tester->dial();
        $tester->send('1');
        $tester->send('1');

        $this->assertSame(2, $calls, 'With dedupe off, each request is processed.');
    }
}
