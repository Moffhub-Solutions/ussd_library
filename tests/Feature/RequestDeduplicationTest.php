<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Tests\Feature;

use Moffhub\Ussd\Builders\FlexibleFormBuilder;
use Moffhub\Ussd\Menus\PaginatedMenu;
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

    /**
     * Regression: pressing "next page" twice must page twice. Paging changes
     * neither the step, the field index nor the menu, so before the fix the
     * second "00" fingerprinted identically to the first, was taken for a
     * gateway retry, and was served page two from cache. The list could never
     * advance past page two however many times the caller pressed next.
     */
    public function test_repeated_next_page_keeps_advancing_a_list(): void
    {
        $items = [];
        for ($index = 1; $index <= 12; $index++) {
            $items[$index] = ['name' => sprintf('Item %02d', $index)];
        }

        $tester = UssdTester::fake(['default_menu' => 'list'])
            ->register('list', new PaginatedMenu('Items', $items, ['max_sms_length' => 120]));

        $tester->dial();
        $this->assertStringContainsString('Page 1 of', $tester->message());

        $tester->send('00');
        $this->assertStringContainsString('Page 2 of', $tester->message());

        $tester->send('00');
        $this->assertStringContainsString('Page 3 of', $tester->message());
    }

    /**
     * The same regression inside a form's option list, which pages via its own
     * form state rather than the menu's.
     */
    public function test_repeated_next_page_keeps_advancing_a_form_option_list(): void
    {
        $options = [];
        for ($index = 1; $index <= 12; $index++) {
            $options[$index] = ['id' => $index, 'name' => sprintf('County %02d', $index)];
        }

        $builder = new FlexibleFormBuilder('Signup');
        $builder->paginatedField('county', 'Select your County:', $options, ['items_per_page' => 5]);

        $tester = UssdTester::fake(['default_menu' => 'signup'])
            ->register('signup', $builder->build());

        $tester->dial();
        $this->assertStringContainsString('Page 1 of 3', $tester->message());

        $tester->send('00');
        $this->assertStringContainsString('Page 2 of 3', $tester->message());

        $tester->send('00');
        $this->assertStringContainsString('Page 3 of 3', $tester->message());
    }
}
