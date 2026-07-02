<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Testing;

use BackedEnum;
use Illuminate\Http\Request;
use Moffhub\Ussd\Interfaces\MenuNameInterface;
use Moffhub\Ussd\Interfaces\UssdMenuInterface;
use Moffhub\Ussd\UssdFramework;
use Moffhub\Ussd\UssdResponse;
use PHPUnit\Framework\Assert;

/**
 * Fluent test harness for driving a USSD session without a real gateway.
 *
 * Drive a whole session as a list of inputs and assert on the responses:
 *
 *     UssdTester::fake()
 *         ->register('main', $mainMenu)
 *         ->register('airtime', $airtimeMenu)
 *         ->dial()->assertSee('Welcome')
 *         ->send('3')->assertSee('Buy Airtime')
 *         ->send('1')->assertEnded()->assertSee('successful');
 *
 * fake() applies test-friendly defaults (rate limiting, analytics, audit
 * logging and the database layer off; debug on so menu exceptions surface),
 * so you do not have to disable a wall of config flags by hand. The phone
 * number and session id stay stable across the session, so the framework
 * treats the first request as a new session and the rest as continuations.
 */
final class UssdTester
{
    private ?UssdResponse $lastResponse = null;

    public function __construct(
        private readonly UssdFramework $framework,
        private string $phoneNumber = '+254700000000',
        private string $sessionId = 'test-session',
        private string $serviceCode = '*123#',
    ) {}

    /**
     * Build a tester around a framework preconfigured for tests.
     *
     * @param  array<string, mixed>  $config  Overrides merged over the test defaults.
     */
    public static function fake(array $config = []): static
    {
        $framework = new UssdFramework(array_replace_recursive([
            'debug' => true,
            'security' => [
                'rate_limiting' => false,
                'input_sanitization' => true,
                'audit_logging' => false,
            ],
            'analytics' => ['enabled' => false],
            'database' => ['enabled' => false],
        ], $config));

        return new self($framework);
    }

    /**
     * The underlying framework, for registering menus, hooks or a provider.
     */
    public function framework(): UssdFramework
    {
        return $this->framework;
    }

    /**
     * Use a specific phone number / session id / service code for this session.
     */
    public function as(string $phoneNumber, ?string $sessionId = null, ?string $serviceCode = null): static
    {
        $this->phoneNumber = $phoneNumber;
        $this->sessionId = $sessionId ?? $this->sessionId;
        $this->serviceCode = $serviceCode ?? $this->serviceCode;

        return $this;
    }

    /**
     * Register a menu (proxies to the framework) and return the tester.
     */
    public function register(string|MenuNameInterface|BackedEnum $name, UssdMenuInterface $menu): static
    {
        $this->framework->registerMenu($name, $menu);

        return $this;
    }

    /**
     * Register several menus at once.
     *
     * @param  array<string, UssdMenuInterface>  $menus
     */
    public function registerMenus(array $menus): static
    {
        $this->framework->registerMenus($menus);

        return $this;
    }

    /**
     * Send the initial dial (empty input, a new session).
     */
    public function dial(): static
    {
        return $this->send('');
    }

    /**
     * Send one input for the current session and capture the response.
     */
    public function send(string $text): static
    {
        $this->lastResponse = $this->framework->handle(Request::create('/', 'POST', [
            'phoneNumber' => $this->phoneNumber,
            'text' => $text,
            'sessionId' => $this->sessionId,
            'serviceCode' => $this->serviceCode,
        ]));

        return $this;
    }

    /**
     * Drive a whole session: an initial dial followed by each input in order.
     *
     * @param  array<int, string>  $inputs
     */
    public function drive(array $inputs): static
    {
        $this->dial();

        foreach ($inputs as $input) {
            $this->send($input);
        }

        return $this;
    }

    /**
     * The most recent response. Throws if nothing has been sent yet.
     */
    public function response(): UssdResponse
    {
        if (! $this->lastResponse instanceof UssdResponse) {
            throw new \LogicException('No USSD request has been sent yet; call dial() or send() first.');
        }

        return $this->lastResponse;
    }

    /**
     * The message body of the most recent response.
     */
    public function message(): string
    {
        return $this->response()->getMessage();
    }

    public function assertSee(string $text): static
    {
        Assert::assertStringContainsString($text, $this->message());

        return $this;
    }

    public function assertDontSee(string $text): static
    {
        Assert::assertStringNotContainsString($text, $this->message());

        return $this;
    }

    /**
     * Assert the session is still open (a CON response prompting for more input).
     */
    public function assertContinue(): static
    {
        Assert::assertTrue(
            $this->response()->isContinue(),
            'Expected a CONTINUE response, got '.$this->response()->getType().'.'
        );

        return $this;
    }

    /**
     * Assert the session has ended (an END response).
     */
    public function assertEnded(): static
    {
        Assert::assertTrue(
            $this->response()->isEnd(),
            'Expected an END response, got '.$this->response()->getType().'.'
        );

        return $this;
    }

    public function assertType(string $type): static
    {
        Assert::assertSame($type, $this->response()->getType());

        return $this;
    }
}
