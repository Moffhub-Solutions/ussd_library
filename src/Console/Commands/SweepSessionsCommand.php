<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Moffhub\Ussd\Events\SessionExpired;
use Moffhub\Ussd\Interfaces\MetricsRecorderInterface;
use Moffhub\Ussd\Metrics\NullMetricsRecorder;
use Moffhub\Ussd\Services\UssdDatabaseService;

/**
 * Finalizes abandoned sessions: ones that expired (inactive past the timeout)
 * without reaching a terminal END. USSD abandoners never send another request,
 * so this cannot be detected inline; this command closes the gap by recording
 * drop-off in ussd_menu_statistics, emitting SessionExpired, and marking the
 * session ended so it is only finalized once. Schedule it (e.g. every minute).
 */
class SweepSessionsCommand extends Command
{
    protected $signature = 'ussd:sweep-sessions
                            {--timeout= : Seconds of inactivity before a session is abandoned (default: config ussd.session_timeout)}
                            {--dry-run : Preview count without finalizing}';

    protected $description = 'Finalize abandoned USSD sessions: record drop-off, emit SessionExpired, mark ended';

    public function handle(): int
    {
        $timeout = $this->option('timeout') !== null
            ? (int) $this->option('timeout')
            : (int) config('ussd.session_timeout', 300);

        $cutoff = Carbon::now()->subSeconds($timeout);

        $sessions = DB::table('ussd_user_sessions')
            ->whereNull('ended_at')
            ->where('completed', false)
            ->where('last_activity', '<', $cutoff)
            ->get();

        if ($sessions->isEmpty()) {
            $this->info('No abandoned sessions to sweep.');

            return self::SUCCESS;
        }

        if ((bool) $this->option('dry-run')) {
            $this->info("[Dry run] Would finalize {$sessions->count()} abandoned session(s).");

            return self::SUCCESS;
        }

        $db = new UssdDatabaseService((array) config('ussd.database', []));
        $metrics = $this->resolveMetrics();
        $now = Carbon::now();
        $swept = 0;

        foreach ($sessions as $session) {
            $lastMenu = $session->current_menu;

            SessionExpired::dispatch(
                (string) $session->session_id,
                (string) $session->phone_number,
                is_string($lastMenu) ? $lastMenu : null,
            );

            if (is_string($lastMenu) && $lastMenu !== '') {
                $db->updateMenuStatistics($lastMenu, null, false, true);
                $metrics->sessionAbandoned($lastMenu);
            }

            DB::table('ussd_user_sessions')
                ->where('id', $session->id)
                ->update(['ended_at' => $now, 'updated_at' => $now]);

            $swept++;
        }

        $this->info("Swept {$swept} abandoned session(s).");

        return self::SUCCESS;
    }

    protected function resolveMetrics(): MetricsRecorderInterface
    {
        return app()->bound(MetricsRecorderInterface::class)
            ? app(MetricsRecorderInterface::class)
            : new NullMetricsRecorder;
    }
}
