<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ListSessionsCommand extends Command
{
    protected $signature = 'ussd:list-sessions
                            {--provider= : Filter by provider name}';

    protected $description = 'Show active USSD sessions in a table format';

    public function handle(): int
    {
        $query = DB::table('ussd_user_sessions')
            ->where('completed', false)
            ->orderByDesc('last_activity');

        $providerFilter = $this->option('provider');

        if (is_string($providerFilter) && $providerFilter !== '') {
            $query->where('session_data', 'like', '%"provider":"'.$providerFilter.'"%');
        }

        $sessions = $query->get();

        if ($sessions->isEmpty()) {
            $this->info('No active sessions found.');

            return self::SUCCESS;
        }

        $rows = $sessions->map(fn ($session) => [
            'session_id' => $session->session_id,
            'phone' => $this->maskPhone($session->phone_number),
            'current_menu' => $session->current_menu ?? 'N/A',
            'started_at' => $session->started_at ?? $session->created_at,
            'last_activity' => $session->last_activity,
        ])->toArray();

        $this->table(
            ['Session ID', 'Phone', 'Current Menu', 'Started At', 'Last Activity'],
            $rows
        );

        $this->info("Total active sessions: {$sessions->count()}");

        return self::SUCCESS;
    }

    protected function maskPhone(string $phone): string
    {
        $length = strlen($phone);

        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        $visibleStart = 3;
        $visibleEnd = 2;
        $maskedLength = $length - $visibleStart - $visibleEnd;

        return substr($phone, 0, $visibleStart).str_repeat('*', max($maskedLength, 0)).substr($phone, -$visibleEnd);
    }
}
