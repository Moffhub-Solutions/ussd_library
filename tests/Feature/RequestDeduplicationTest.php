<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Tests\Feature;

use Moffhub\Ussd\Builders\FlexibleFormBuilder;
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

    /**
     * Regression: identical consecutive keystrokes at different form steps must
     * not be treated as duplicates. The dedupe key includes the session position
     * (step / form field index / state / menu), so the same "1" pressed at the
     * next field lands at a different position and a different key. Before the
     * fix the key ignored position and the second "1" was served the first
     * step's cached response, so the form never advanced past the first field.
     * (The provider still sends the accumulated trail "1" then "1*1"; only the
     * parsed segment "1" reaches processing.)
     */
    public function test_same_input_at_consecutive_steps_is_not_deduped(): void
    {
        $builder = new FlexibleFormBuilder('Signup');
        $builder->textField('first', 'Enter first:');
        $builder->textField('second', 'Enter second:');
        $menu = $builder->build();

        $tester = UssdTester::fake(['default_menu' => 'signup'])
            ->register('signup', $menu);

        $tester->dial();                 // shows the first field
        $tester->send('1');              // trail "1": stores first, shows second field
        $this->assertTrue($tester->response()->isContinue());
        $this->assertStringContainsString('second', $tester->message());

        $tester->send('1*1');            // trail "1*1": parses to "1", stores second, completes
        $this->assertTrue(
            $tester->response()->isEnd(),
            'The second "1" is a distinct step, not a retry; the form must advance and complete.'
        );
    }
}
