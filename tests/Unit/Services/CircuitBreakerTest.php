<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Tests\Unit\Services;

use Moffhub\Ussd\Services\CircuitBreaker;
use Moffhub\Ussd\Tests\TestCase;

class CircuitBreakerTest extends TestCase
{
    private CircuitBreaker $circuitBreaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->circuitBreaker = new CircuitBreaker(threshold: 3, cooldownSeconds: 5);
    }

    protected function tearDown(): void
    {
        $this->circuitBreaker->reset('https://api.example.com/data');
        parent::tearDown();
    }

    public function test_initial_state_is_closed(): void
    {
        $state = $this->circuitBreaker->getState('https://api.example.com/data');

        $this->assertEquals(CircuitBreaker::STATE_CLOSED, $state);
    }

    public function test_is_available_when_closed(): void
    {
        $this->assertTrue($this->circuitBreaker->isAvailable('https://api.example.com/data'));
    }

    public function test_failure_count_increments(): void
    {
        $endpoint = 'https://api.example.com/data';

        $this->circuitBreaker->recordFailure($endpoint);
        $this->assertEquals(1, $this->circuitBreaker->getFailureCount($endpoint));

        $this->circuitBreaker->recordFailure($endpoint);
        $this->assertEquals(2, $this->circuitBreaker->getFailureCount($endpoint));
    }

    public function test_circuit_opens_after_threshold_failures(): void
    {
        $endpoint = 'https://api.example.com/data';

        for ($i = 0; $i < 3; $i++) {
            $this->circuitBreaker->recordFailure($endpoint);
        }

        $this->assertEquals(CircuitBreaker::STATE_OPEN, $this->circuitBreaker->getState($endpoint));
        $this->assertFalse($this->circuitBreaker->isAvailable($endpoint));
    }

    public function test_circuit_stays_closed_below_threshold(): void
    {
        $endpoint = 'https://api.example.com/data';

        $this->circuitBreaker->recordFailure($endpoint);
        $this->circuitBreaker->recordFailure($endpoint);

        $this->assertEquals(CircuitBreaker::STATE_CLOSED, $this->circuitBreaker->getState($endpoint));
        $this->assertTrue($this->circuitBreaker->isAvailable($endpoint));
    }

    public function test_success_resets_failure_count_in_closed_state(): void
    {
        $endpoint = 'https://api.example.com/data';

        $this->circuitBreaker->recordFailure($endpoint);
        $this->circuitBreaker->recordFailure($endpoint);
        $this->circuitBreaker->recordSuccess($endpoint);

        $this->assertEquals(0, $this->circuitBreaker->getFailureCount($endpoint));
    }

    public function test_reset_clears_all_state(): void
    {
        $endpoint = 'https://api.example.com/data';

        for ($i = 0; $i < 3; $i++) {
            $this->circuitBreaker->recordFailure($endpoint);
        }

        $this->assertEquals(CircuitBreaker::STATE_OPEN, $this->circuitBreaker->getState($endpoint));

        $this->circuitBreaker->reset($endpoint);

        $this->assertEquals(CircuitBreaker::STATE_CLOSED, $this->circuitBreaker->getState($endpoint));
        $this->assertEquals(0, $this->circuitBreaker->getFailureCount($endpoint));
        $this->assertTrue($this->circuitBreaker->isAvailable($endpoint));
    }

    public function test_success_in_half_open_closes_circuit(): void
    {
        $endpoint = 'https://api.example.com/data';

        // Use a circuit breaker with 0 cooldown for testing
        $cb = new CircuitBreaker(threshold: 2, cooldownSeconds: 0);

        $cb->recordFailure($endpoint);
        $cb->recordFailure($endpoint);

        $this->assertEquals(CircuitBreaker::STATE_OPEN, $cb->getState($endpoint));

        // With 0 cooldown, isAvailable should transition to half-open
        $this->assertTrue($cb->isAvailable($endpoint));
        $this->assertEquals(CircuitBreaker::STATE_HALF_OPEN, $cb->getState($endpoint));

        // Success in half-open should close the circuit
        $cb->recordSuccess($endpoint);

        $this->assertEquals(CircuitBreaker::STATE_CLOSED, $cb->getState($endpoint));

        $cb->reset($endpoint);
    }

    public function test_failure_in_half_open_reopens_circuit(): void
    {
        $endpoint = 'https://api.example.com/data';

        $cb = new CircuitBreaker(threshold: 2, cooldownSeconds: 0);

        $cb->recordFailure($endpoint);
        $cb->recordFailure($endpoint);

        // Transition to half-open via isAvailable with expired cooldown
        $cb->isAvailable($endpoint);
        $this->assertEquals(CircuitBreaker::STATE_HALF_OPEN, $cb->getState($endpoint));

        // Failure in half-open should re-open
        $cb->recordFailure($endpoint);

        $this->assertEquals(CircuitBreaker::STATE_OPEN, $cb->getState($endpoint));

        $cb->reset($endpoint);
    }

    public function test_different_endpoints_have_independent_state(): void
    {
        $endpoint1 = 'https://api1.example.com/data';
        $endpoint2 = 'https://api2.example.com/data';

        for ($i = 0; $i < 3; $i++) {
            $this->circuitBreaker->recordFailure($endpoint1);
        }

        $this->assertEquals(CircuitBreaker::STATE_OPEN, $this->circuitBreaker->getState($endpoint1));
        $this->assertEquals(CircuitBreaker::STATE_CLOSED, $this->circuitBreaker->getState($endpoint2));
        $this->assertFalse($this->circuitBreaker->isAvailable($endpoint1));
        $this->assertTrue($this->circuitBreaker->isAvailable($endpoint2));

        $this->circuitBreaker->reset($endpoint1);
        $this->circuitBreaker->reset($endpoint2);
    }
}
