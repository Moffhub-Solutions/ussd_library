<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Tests\Unit\Console;

use Illuminate\Support\Facades\DB;
use Moffhub\Ussd\Tests\TestCase;

class CommandsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__.'/../../../database/migrations');
    }

    // CleanupSessionsCommand tests

    public function test_cleanup_sessions_with_dry_run(): void
    {
        DB::table('ussd_user_sessions')->insert([
            'session_id' => 'old_session',
            'phone_number' => '+254712345678',
            'current_menu' => 'main',
            'session_data' => '{}',
            'started_at' => now()->subDays(2),
            'last_activity' => now()->subDays(2),
            'total_interactions' => 1,
            'user_journey' => '[]',
            'completed' => false,
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);

        $this->artisan('ussd:cleanup-sessions', ['--dry-run' => true])
            ->expectsOutputToContain('Would delete 1 expired session(s)')
            ->assertExitCode(0);

        // Session should still exist
        $this->assertDatabaseHas('ussd_user_sessions', ['session_id' => 'old_session']);
    }

    public function test_cleanup_sessions_deletes_old_sessions(): void
    {
        DB::table('ussd_user_sessions')->insert([
            'session_id' => 'old_session',
            'phone_number' => '+254712345678',
            'current_menu' => 'main',
            'session_data' => '{}',
            'started_at' => now()->subDays(2),
            'last_activity' => now()->subDays(2),
            'total_interactions' => 1,
            'user_journey' => '[]',
            'completed' => false,
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);

        $this->artisan('ussd:cleanup-sessions', ['--older-than' => '24h'])
            ->expectsOutputToContain('Deleted 1 expired session(s)')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('ussd_user_sessions', ['session_id' => 'old_session']);
    }

    public function test_cleanup_sessions_keeps_recent_sessions(): void
    {
        DB::table('ussd_user_sessions')->insert([
            'session_id' => 'recent_session',
            'phone_number' => '+254712345678',
            'current_menu' => 'main',
            'session_data' => '{}',
            'started_at' => now(),
            'last_activity' => now(),
            'total_interactions' => 1,
            'user_journey' => '[]',
            'completed' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('ussd:cleanup-sessions', ['--older-than' => '24h'])
            ->expectsOutputToContain('No expired sessions found')
            ->assertExitCode(0);

        $this->assertDatabaseHas('ussd_user_sessions', ['session_id' => 'recent_session']);
    }

    public function test_cleanup_sessions_invalid_duration(): void
    {
        $this->artisan('ussd:cleanup-sessions', ['--older-than' => 'invalid'])
            ->expectsOutputToContain('Invalid duration format')
            ->assertExitCode(1);
    }

    // ListSessionsCommand tests

    public function test_list_sessions_shows_active_sessions(): void
    {
        DB::table('ussd_user_sessions')->insert([
            'session_id' => 'active_session_1',
            'phone_number' => '+254712345678',
            'current_menu' => 'main',
            'session_data' => '{}',
            'started_at' => now(),
            'last_activity' => now(),
            'total_interactions' => 3,
            'user_journey' => '[]',
            'completed' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('ussd:list-sessions')
            ->expectsOutputToContain('active_session_1')
            ->expectsOutputToContain('Total active sessions: 1')
            ->assertExitCode(0);
    }

    public function test_list_sessions_no_active_sessions(): void
    {
        $this->artisan('ussd:list-sessions')
            ->expectsOutputToContain('No active sessions found')
            ->assertExitCode(0);
    }

    // HealthCheckCommand tests

    public function test_health_check_reports_healthy(): void
    {
        $this->artisan('ussd:health')
            ->expectsOutputToContain('Cache is connected and operational')
            ->expectsOutputToContain('Database is connected')
            ->expectsOutputToContain('Overall status: HEALTHY')
            ->assertExitCode(0);
    }

    // ManageAccessCommand tests

    public function test_manage_access_add_to_whitelist(): void
    {
        $this->artisan('ussd:manage-access', [
            'action' => 'add',
            '--type' => 'whitelist',
            '--phone' => '+254712345678',
            '--reason' => 'Testing',
        ])
            ->expectsOutputToContain('Added +254712345678 to whitelist')
            ->assertExitCode(0);

        $this->assertDatabaseHas('ussd_access_lists', [
            'phone_number' => '+254712345678',
            'type' => 'whitelist',
            'is_active' => true,
        ]);
    }

    public function test_manage_access_add_to_blacklist(): void
    {
        $this->artisan('ussd:manage-access', [
            'action' => 'add',
            '--type' => 'blacklist',
            '--phone' => '+254999999999',
        ])
            ->expectsOutputToContain('Added +254999999999 to blacklist')
            ->assertExitCode(0);

        $this->assertDatabaseHas('ussd_access_lists', [
            'phone_number' => '+254999999999',
            'type' => 'blacklist',
        ]);
    }

    public function test_manage_access_remove_from_whitelist(): void
    {
        DB::table('ussd_access_lists')->insert([
            'phone_number' => '+254712345678',
            'type' => 'whitelist',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('ussd:manage-access', [
            'action' => 'remove',
            '--type' => 'whitelist',
            '--phone' => '+254712345678',
        ])
            ->expectsOutputToContain('Removed +254712345678 from whitelist')
            ->assertExitCode(0);
    }

    public function test_manage_access_list_entries(): void
    {
        DB::table('ussd_access_lists')->insert([
            'phone_number' => '+254712345678',
            'type' => 'whitelist',
            'reason' => 'VIP user',
            'added_by' => 'admin',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('ussd:manage-access', [
            'action' => 'list',
            '--type' => 'whitelist',
        ])
            ->expectsOutputToContain('Total whitelist entries: 1')
            ->assertExitCode(0);
    }

    public function test_manage_access_invalid_action(): void
    {
        $this->artisan('ussd:manage-access', [
            'action' => 'invalid',
            '--type' => 'whitelist',
        ])
            ->expectsOutputToContain('Invalid action')
            ->assertExitCode(1);
    }

    public function test_manage_access_invalid_type(): void
    {
        $this->artisan('ussd:manage-access', [
            'action' => 'list',
            '--type' => 'invalid',
        ])
            ->expectsOutputToContain('Invalid type')
            ->assertExitCode(1);
    }

    public function test_manage_access_add_requires_phone(): void
    {
        $this->artisan('ussd:manage-access', [
            'action' => 'add',
            '--type' => 'whitelist',
        ])
            ->expectsOutputToContain('Phone number is required')
            ->assertExitCode(1);
    }
}
