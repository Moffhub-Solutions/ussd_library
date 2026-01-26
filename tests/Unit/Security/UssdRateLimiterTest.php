<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Tests\Unit\Security;

use Illuminate\Support\Facades\Cache;
use Moffhub\Ussd\Security\UssdRateLimiter;
use Moffhub\Ussd\Tests\TestCase;

class UssdRateLimiterTest extends TestCase
{
    private UssdRateLimiter $limiter;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->limiter = new UssdRateLimiter([
            'max_requests_per_minute' => 5,
            'max_requests_per_hour' => 50,
            'max_requests_per_day' => 100,
            'blocked_duration' => 60,
            'whitelist' => ['+254700000000'],
            'blacklist' => ['+254999999999'],
            'enabled' => true,
        ]);
    }

    public function test_allow_returns_true_for_first_request(): void
    {
        $this->assertTrue($this->limiter->allow('+254712345678'));
    }

    public function test_allow_returns_true_within_limits(): void
    {
        $phoneNumber = '+254712345678';

        for ($i = 0; $i < 4; $i++) {
            $this->assertTrue($this->limiter->allow($phoneNumber));
        }
    }

    public function test_allow_returns_false_when_limit_exceeded(): void
    {
        $phoneNumber = '+254712345678';

        // Exhaust the limit
        for ($i = 0; $i < 5; $i++) {
            $this->limiter->allow($phoneNumber);
        }

        // Next request should be blocked
        $this->assertFalse($this->limiter->allow($phoneNumber));
    }

    public function test_whitelisted_numbers_always_allowed(): void
    {
        $whitelistedNumber = '+254700000000';

        // Make many requests - should all be allowed
        for ($i = 0; $i < 20; $i++) {
            $this->assertTrue($this->limiter->allow($whitelistedNumber));
        }
    }

    public function test_blacklisted_numbers_never_allowed(): void
    {
        $blacklistedNumber = '+254999999999';

        $this->assertFalse($this->limiter->allow($blacklistedNumber));
    }

    public function test_manual_block_blocks_user(): void
    {
        $phoneNumber = '+254712345678';

        $this->limiter->manualBlock($phoneNumber, 300);

        $this->assertFalse($this->limiter->allow($phoneNumber));
    }

    public function test_unblock_unblocks_user(): void
    {
        $phoneNumber = '+254712345678';

        $this->limiter->manualBlock($phoneNumber);
        $this->limiter->unblock($phoneNumber);

        $this->assertTrue($this->limiter->allow($phoneNumber));
    }

    public function test_get_status_returns_correct_info(): void
    {
        $phoneNumber = '+254712345678';

        $this->limiter->allow($phoneNumber);
        $this->limiter->allow($phoneNumber);

        $status = $this->limiter->getStatus($phoneNumber);

        $this->assertFalse($status['whitelisted']);
        $this->assertFalse($status['blacklisted']);
        $this->assertFalse($status['blocked']);
        $this->assertEquals(2, $status['requests_minute']);
        $this->assertArrayHasKey('limits', $status);
    }

    public function test_get_status_shows_whitelisted(): void
    {
        $status = $this->limiter->getStatus('+254700000000');

        $this->assertTrue($status['whitelisted']);
    }

    public function test_get_status_shows_blacklisted(): void
    {
        $status = $this->limiter->getStatus('+254999999999');

        $this->assertTrue($status['blacklisted']);
    }

    public function test_get_status_shows_blocked(): void
    {
        $phoneNumber = '+254712345678';

        $this->limiter->manualBlock($phoneNumber);
        $status = $this->limiter->getStatus($phoneNumber);

        $this->assertTrue($status['blocked']);
    }

    public function test_disabled_limiter_always_allows(): void
    {
        $limiter = new UssdRateLimiter([
            'enabled' => false,
            'max_requests_per_minute' => 1,
        ]);

        $phoneNumber = '+254712345678';

        // Should allow even beyond limits
        for ($i = 0; $i < 10; $i++) {
            $this->assertTrue($limiter->allow($phoneNumber));
        }
    }

    public function test_different_actions_have_separate_limits(): void
    {
        $phoneNumber = '+254712345678';

        // Exhaust limit for 'request' action
        for ($i = 0; $i < 5; $i++) {
            $this->limiter->allow($phoneNumber, 'request');
        }

        // Different action should still be allowed
        $this->assertTrue($this->limiter->allow($phoneNumber, 'other_action'));
    }
}
