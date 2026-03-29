<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Tests\Unit;

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
        $entry = UssdAccessList::factory()->create();

        $this->assertNotNull($entry->id);
        $this->assertNotNull($entry->phone_number);
        $this->assertEquals('whitelist', $entry->type);
        $this->assertTrue($entry->is_active);
    }

    public function test_access_list_factory_whitelist_state(): void
    {
        $entry = UssdAccessList::factory()->whitelist()->create();

        $this->assertEquals('whitelist', $entry->type);
    }

    public function test_access_list_factory_blacklist_state(): void
    {
        $entry = UssdAccessList::factory()->blacklist()->create();

        $this->assertEquals('blacklist', $entry->type);
    }

    public function test_access_list_factory_inactive_state(): void
    {
        $entry = UssdAccessList::factory()->inactive()->create();

        $this->assertFalse($entry->is_active);
    }

    public function test_access_list_factory_expired_state(): void
    {
        $entry = UssdAccessList::factory()->expired()->create();

        $this->assertNotNull($entry->expires_at);
        $this->assertTrue($entry->expires_at->isPast());
    }

    public function test_access_list_factory_creates_multiple(): void
    {
        $entries = UssdAccessList::factory()->count(3)->create();

        $this->assertCount(3, $entries);
    }

    public function test_access_list_factory_make_does_not_persist(): void
    {
        $entry = UssdAccessList::factory()->make();

        $this->assertNull($entry->id);
        $this->assertNotNull($entry->phone_number);
    }

    public function test_menu_statistic_factory_creates_default_instance(): void
    {
        $stat = UssdMenuStatistic::factory()->create();

        $this->assertNotNull($stat->id);
        $this->assertEquals('main_menu', $stat->menu_name);
        $this->assertNotNull($stat->option_selected);
        $this->assertGreaterThanOrEqual(1, $stat->access_count);
    }

    public function test_menu_statistic_factory_for_menu(): void
    {
        $stat = UssdMenuStatistic::factory()->forMenu('account_menu')->create();

        $this->assertEquals('account_menu', $stat->menu_name);
    }

    public function test_menu_statistic_factory_for_date(): void
    {
        $stat = UssdMenuStatistic::factory()->forDate('2025-01-15')->create();

        $this->assertEquals('2025-01-15', $stat->date);
    }

    public function test_menu_statistic_factory_creates_multiple(): void
    {
        $stats = UssdMenuStatistic::factory()->count(5)->create();

        $this->assertCount(5, $stats);
    }
}
