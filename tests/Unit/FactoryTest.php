<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Tests\Unit;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Moffhub\Ussd\Models\UssdAccessList;
use Moffhub\Ussd\Models\UssdMenuStatistic;
use Moffhub\Ussd\Tests\TestCase;

class FactoryTest extends TestCase
{
    use RefreshDatabase;

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('ussd.database.enabled', true);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }

    public function test_access_list_factory_creates_default_instance(): void
    {
        $result = UssdAccessList::factory()->create();
        $this->assertInstanceOf(UssdAccessList::class, $result);
        $entry = $result;

        $this->assertGreaterThan(0, $entry->id);
        $this->assertNotEmpty($entry->phone_number);
        $this->assertEquals('whitelist', $entry->type);
        $this->assertTrue($entry->is_active);
    }

    public function test_access_list_factory_whitelist_state(): void
    {
        $result = UssdAccessList::factory()->whitelist()->create();
        $this->assertInstanceOf(UssdAccessList::class, $result);
        $entry = $result;

        $this->assertEquals('whitelist', $entry->type);
    }

    public function test_access_list_factory_blacklist_state(): void
    {
        $result = UssdAccessList::factory()->blacklist()->create();
        $this->assertInstanceOf(UssdAccessList::class, $result);
        $entry = $result;

        $this->assertEquals('blacklist', $entry->type);
    }

    public function test_access_list_factory_inactive_state(): void
    {
        $result = UssdAccessList::factory()->inactive()->create();
        $this->assertInstanceOf(UssdAccessList::class, $result);
        $entry = $result;

        $this->assertFalse($entry->is_active);
    }

    public function test_access_list_factory_expired_state(): void
    {
        $result = UssdAccessList::factory()->expired()->create();
        $this->assertInstanceOf(UssdAccessList::class, $result);
        $entry = $result;

        $this->assertNotNull($entry->expires_at);
        $this->assertTrue($entry->expires_at->isPast());
    }

    public function test_access_list_factory_creates_multiple(): void
    {
        $entries = UssdAccessList::factory()->count(3)->create();
        $this->assertInstanceOf(Collection::class, $entries);

        $this->assertCount(3, $entries);
    }

    public function test_access_list_factory_make_does_not_persist(): void
    {
        $result = UssdAccessList::factory()->make();
        $this->assertInstanceOf(UssdAccessList::class, $result);
        $entry = $result;

        $this->assertNull($entry->getKey());
        $this->assertNotEmpty($entry->phone_number);
    }

    public function test_menu_statistic_factory_creates_default_instance(): void
    {
        $result = UssdMenuStatistic::factory()->create();
        $this->assertInstanceOf(UssdMenuStatistic::class, $result);
        $stat = $result;

        $this->assertGreaterThan(0, $stat->id);
        $this->assertEquals('main_menu', $stat->menu_name);
        $this->assertNotEmpty($stat->option_selected);
        $this->assertGreaterThanOrEqual(1, $stat->access_count);
    }

    public function test_menu_statistic_factory_for_menu(): void
    {
        $result = UssdMenuStatistic::factory()->forMenu('account_menu')->create();
        $this->assertInstanceOf(UssdMenuStatistic::class, $result);
        $stat = $result;

        $this->assertEquals('account_menu', $stat->menu_name);
    }

    public function test_menu_statistic_factory_for_date(): void
    {
        $result = UssdMenuStatistic::factory()->forDate('2025-01-15')->create();
        $this->assertInstanceOf(UssdMenuStatistic::class, $result);
        $stat = $result;

        $this->assertEquals('2025-01-15', $stat->date);
    }

    public function test_menu_statistic_factory_creates_multiple(): void
    {
        $stats = UssdMenuStatistic::factory()->count(5)->create();
        $this->assertInstanceOf(Collection::class, $stats);

        $this->assertCount(5, $stats);
    }
}
