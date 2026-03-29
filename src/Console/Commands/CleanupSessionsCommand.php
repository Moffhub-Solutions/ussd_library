<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupSessionsCommand extends Command
{
    protected $signature = 'ussd:cleanup-sessions
                            {--older-than=24h : Delete sessions older than this duration (e.g., 24h, 7d, 30m)}
                            {--dry-run : Preview count without deleting}';

    protected $description = 'Delete expired USSD sessions beyond the retention period';

    public function handle(): int
    {
        $olderThan = $this->option('older-than');
        $dryRun = (bool) $this->option('dry-run');

        $cutoff = $this->parseDuration((string) $olderThan);

        if ($cutoff === null) {
            $this->error("Invalid duration format: {$olderThan}. Use formats like 24h, 7d, or 30m.");

            return self::FAILURE;
        }

        $query = DB::table('ussd_user_sessions')
            ->where('last_activity', '<', $cutoff);

        $count = $query->count();

        if ($count === 0) {
            $this->info('No expired sessions found to clean up.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info("[Dry run] Would delete {$count} expired session(s) older than {$olderThan}.");

            return self::SUCCESS;
        }

        $deleted = $query->delete();

        $this->info("Deleted {$deleted} expired session(s) older than {$olderThan}.");

        return self::SUCCESS;
    }

    protected function parseDuration(string $duration): ?Carbon
    {
        if (preg_match('/^(\d+)(m|h|d)$/', $duration, $matches)) {
            $value = (int) $matches[1];
            $unit = $matches[2];

            return match ($unit) {
                'm' => Carbon::now()->subMinutes($value),
                'h' => Carbon::now()->subHours($value),
                'd' => Carbon::now()->subDays($value),
            };
        }

        return null;
    }
}
