<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Tests\Unit\Database;

use Illuminate\Support\Facades\Schema;
use Moffhub\Ussd\Tests\TestCase;

class UssdMigrationTest extends TestCase
{
    private const TABLES = [
        'ussd_audit_logs',
        'ussd_analytics',
        'ussd_performance_metrics',
        'ussd_user_sessions',
        'ussd_rate_limits',
        'ussd_security_events',
        'ussd_menu_statistics',
        'ussd_business_metrics',
        'ussd_sessions',
        'ussd_session_analytics',
        'ussd_session_recovery_logs',
        'ussd_access_lists',
    ];

    private function runMigrationUp(): void
    {
        $migration = require __DIR__.'/../../../database/migrations/2024_01_01_000000_create_ussd_tables.php';

        if (is_object($migration) && method_exists($migration, 'up')) {
            $migration->up();
        }
    }

    public function test_up_creates_all_ussd_tables(): void
    {
        $this->runMigrationUp();

        foreach (self::TABLES as $table) {
            $this->assertTrue(Schema::hasTable($table), "Expected table to be created: {$table}");
        }
    }

    public function test_up_is_idempotent(): void
    {
        $this->runMigrationUp();

        // A second run must not throw even though the tables already exist
        // (regression for the untimestamped -> timestamped filename rename, which
        // would otherwise attempt to re-create existing tables on older installs).
        $this->runMigrationUp();

        $this->assertTrue(Schema::hasTable('ussd_user_sessions'));
    }
}
