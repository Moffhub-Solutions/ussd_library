<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Tests\Feature;

use Moffhub\Ussd\Builders\FlexibleFormBuilder;
use Moffhub\Ussd\Testing\UssdTester;
use Moffhub\Ussd\Tests\TestCase;
use RuntimeException;

/**
 * The interactive path used to swallow every Exception and re-display a generic
 * message, so a real bug looked like "the menu didn't advance". Completion now
 * logs and, in debug mode, rethrows, mirroring UssdFramework::handle().
 */
class ErrorSurfacingTest extends TestCase
{
    private function throwingForm(): FlexibleFormBuilder
    {
        $builder = new FlexibleFormBuilder(
            'Signup',
            function (): void {
                throw new RuntimeException('onComplete boom');
            }
        );
        $builder->textField('name', 'Enter name:');

        return $builder;
    }

    public function test_debug_mode_rethrows_a_throwing_oncomplete(): void
    {
        $tester = UssdTester::fake(['default_menu' => 'signup', 'debug' => true])
            ->register('signup', $this->throwingForm()->build());

        $tester->dial();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('onComplete boom');
        $tester->send('John');   // completes the single field -> onComplete throws
    }

    public function test_production_mode_stays_graceful_but_does_not_pretend_success(): void
    {
        $tester = UssdTester::fake(['default_menu' => 'signup', 'debug' => false])
            ->register('signup', $this->throwingForm()->build());

        $tester->dial();
        $tester->send('John');

        $response = $tester->response();
        $this->assertTrue($response->isEnd());
        $this->assertStringContainsString('error', strtolower($response->getMessage()));
        $this->assertStringNotContainsString('successfully', strtolower($response->getMessage()));
    }
}
