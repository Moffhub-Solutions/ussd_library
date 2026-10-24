<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Tests\Feature;

use Moffhub\Ussd\Builders\FlexibleFormBuilder;
use Moffhub\Ussd\Menus\SimpleMenu;
use Moffhub\Ussd\Testing\UssdTester;
use Moffhub\Ussd\Tests\TestCase;
use Moffhub\Ussd\UssdResponse;

/**
 * Back inside a form is one question back, or one page back in a long list, and only from the first question does it
 * leave the form for the menu before it.
 */
class FlexibleFormBackTest extends TestCase
{
    private function tester(): UssdTester
    {
        $wards = array_map(static fn (int $n): array => ['id' => 'w'.$n, 'name' => 'Ward '.$n], range(1, 12));
        $form = (new FlexibleFormBuilder('Register', fn ($session, $data) => UssdResponse::end('Done')))
            ->textField('first', 'First name?')
            ->searchableField('ward', 'Ward?', fn () => $wards, ['name'], ['items_per_page' => 5, 'item_formatter' => fn ($k, $i) => $i['name']])
            ->textField('last', 'Last name?')
            ->build();

        $home = new SimpleMenu('Home', ['1' => 'Register'], [
            '1' => fn ($input, $session, $framework) => $framework->navigateToMenuWithResponse('register'),
        ]);

        return UssdTester::fake(['default_menu' => 'home'])->register('home', $home)->register('register', $form);
    }

    public function test_back_in_a_long_list_goes_a_page_back_then_to_the_question_before(): void
    {
        $tester = $this->tester();

        $tester->dial();
        $tester->send('1');
        $tester->send('Jane');
        $tester->send('00');
        $this->assertStringContainsString('1. Ward 6', $tester->message());

        $tester->send('99');
        $this->assertStringContainsString('1. Ward 1', $tester->message());

        $tester->send('99');
        $this->assertStringContainsString('First name?', $tester->message());

        // And what is typed next is the answer to that question, not a leftover page command.
        $tester->send('Janet');
        $this->assertStringContainsString('Ward?', $tester->message());
    }

    public function test_back_from_a_search_clears_it_and_back_from_the_first_question_leaves_the_form(): void
    {
        $tester = $this->tester();

        $tester->dial();
        $tester->send('1');
        $tester->send('Jane');
        $tester->send('98');
        $tester->send('ward 1');
        $this->assertStringContainsString("Search: 'ward 1'", $tester->message());

        $tester->send('99');
        $this->assertStringNotContainsString('Search:', $tester->message());
        $this->assertStringContainsString('Ward 5', $tester->message());

        $tester->send('99');
        $tester->send('99');
        $this->assertStringContainsString('Register', $tester->message());
    }
}
