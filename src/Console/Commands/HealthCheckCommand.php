<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class HealthCheckCommand extends Command
{
    protected $signature = 'ussd:health';

    protected $description = 'Check the health of the USSD framework (cache, database, active sessions, error rate)';

    public function handle(): int
    {
        $this->info('USSD Framework Health Check');
        $this->line('');

        $allHealthy = true;

        // Check cache connectivity
        $cacheOk = $this->checkCache();
        $allHealthy = $allHealthy && $cacheOk;

        // Check database connectivity
        $dbOk = $this->checkDatabase();
        $allHealthy = $allHealthy && $dbOk;

        // Report active sessions
        $this->reportActiveSessions();

        // Report error rate
        $this->reportErrorRate();

        $this->line('');

        if ($allHealthy) {
            $this->info('Overall status: HEALTHY');

            return self::SUCCESS;
        }

        $this->error('Overall status: UNHEALTHY');

        return self::FAILURE;
    }

    protected function checkCache(): bool
    {
        try {
            $testKey = 'ussd_health_check_'.uniqid();
            Cache::put($testKey, 'ok', 10);
            $value = Cache::get($testKey);
            Cache::forget($testKey);

            if ($value === 'ok') {
                $this->line('  [OK] Cache is connected and operational.');

                return true;
            }

            $this->line('  [FAIL] Cache read/write verification failed.');

            return false;
        } catch (\Exception $e) {
            $this->line("  [FAIL] Cache error: {$e->getMessage()}");

            return false;
        }
    }

    protected function checkDatabase(): bool
    {
        try {
            DB::connection()->getPdo();
            $this->line('  [OK] Database is connected.');

            return true;
        } catch (\Exception $e) {
            $this->line("  [FAIL] Database error: {$e->getMessage()}");

            return false;
        }
    }

    protected function reportActiveSessions(): void
    {
        try {
            $count = DB::table('ussd_user_sessions')
                ->where('completed', false)
                ->count();

            $this->line("  [INFO] Active sessions: {$count}");
        } catch (\Exception) {
            $this->line('  [WARN] Could not query active sessions (table may not exist).');
        }
    }

    protected function reportErrorRate(): void
    {
        try {
            $oneHourAgo = now()->subHour();

            $total = DB::table('ussd_audit_logs')
                ->where('created_at', '>=', $oneHourAgo)
                ->count();

            $errors = DB::table('ussd_audit_logs')
                ->where('created_at', '>=', $oneHourAgo)
                ->where('level', 'error')
                ->count();

            $rate = $total > 0 ? round(($errors / $total) * 100, 2) : 0.0;

            $this->line("  [INFO] Error rate (last hour): {$rate}% ({$errors}/{$total} requests)");
        } catch (\Exception) {
            $this->line('  [WARN] Could not query error rate (table may not exist).');
        }
    }
}
