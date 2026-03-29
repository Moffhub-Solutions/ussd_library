<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Tests\Unit\Analytics;

use Illuminate\Support\Facades\Queue;
use Moffhub\Ussd\Analytics\UssdAnalytics;
use Moffhub\Ussd\Jobs\FlushAnalyticsBuffer;
use Moffhub\Ussd\Tests\TestCase;

class UssdAnalyticsFlushTest extends TestCase
{
    public function test_default_flush_strategy_is_sync(): void
    {
        $analytics = new UssdAnalytics(['store_in_database' => false]);

        $this->assertEquals('sync', $analytics->getFlushStrategy());
    }

    public function test_flush_strategy_can_be_set_to_async(): void
    {
        $analytics = new UssdAnalytics([
            'store_in_database' => false,
            'flush_strategy' => 'async',
        ]);

        $this->assertEquals('async', $analytics->getFlushStrategy());
    }

    public function test_flush_strategy_can_be_set_to_shutdown(): void
    {
        $analytics = new UssdAnalytics([
            'store_in_database' => false,
            'flush_strategy' => 'shutdown',
        ]);

        $this->assertEquals('shutdown', $analytics->getFlushStrategy());
    }

    public function test_events_are_buffered(): void
    {
        $analytics = new UssdAnalytics([
            'enabled' => true,
            'store_in_database' => false,
            'buffer_size' => 1000,
            'real_time_dashboard' => false,
        ]);

        $analytics->trackSession('+254712345678', 'start');

        $buffer = $analytics->getBuffer();
        $this->assertNotEmpty($buffer);
        $this->assertEquals('session', $buffer[0]['event_type']);
    }

    public function test_async_flush_dispatches_job(): void
    {
        Queue::fake();

        $analytics = new UssdAnalytics([
            'enabled' => true,
            'store_in_database' => true,
            'buffer_size' => 1000,
            'flush_strategy' => 'async',
            'real_time_dashboard' => false,
        ]);

        $analytics->trackSession('+254712345678', 'start');
        $analytics->flushBuffer();

        Queue::assertPushed(FlushAnalyticsBuffer::class);
    }

    public function test_async_flush_clears_buffer(): void
    {
        Queue::fake();

        $analytics = new UssdAnalytics([
            'enabled' => true,
            'store_in_database' => true,
            'buffer_size' => 1000,
            'flush_strategy' => 'async',
            'real_time_dashboard' => false,
        ]);

        $analytics->trackSession('+254712345678', 'start');
        $this->assertNotEmpty($analytics->getBuffer());

        $analytics->flushBuffer();

        $this->assertEmpty($analytics->getBuffer());
    }

    public function test_sync_flush_does_not_dispatch_job(): void
    {
        Queue::fake();

        $analytics = new UssdAnalytics([
            'enabled' => true,
            'store_in_database' => false,
            'buffer_size' => 1000,
            'flush_strategy' => 'sync',
            'real_time_dashboard' => false,
        ]);

        $analytics->trackSession('+254712345678', 'start');
        $analytics->flushBuffer();

        Queue::assertNotPushed(FlushAnalyticsBuffer::class);
    }

    public function test_flush_returns_false_when_buffer_empty(): void
    {
        $analytics = new UssdAnalytics([
            'enabled' => true,
            'store_in_database' => true,
        ]);

        $this->assertFalse($analytics->flushBuffer());
    }

    public function test_flush_returns_false_when_database_disabled(): void
    {
        $analytics = new UssdAnalytics([
            'enabled' => true,
            'store_in_database' => false,
            'real_time_dashboard' => false,
        ]);

        $analytics->trackSession('+254712345678', 'start');

        $this->assertFalse($analytics->flushBuffer());
    }
}
