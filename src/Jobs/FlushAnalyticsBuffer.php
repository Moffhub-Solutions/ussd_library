<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FlushAnalyticsBuffer implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int, array<string, mixed>>  $buffer
     */
    public function __construct(
        protected array $buffer,
        protected string $tableName = 'ussd_analytics',
    ) {}

    public function handle(): void
    {
        if ($this->buffer === []) {
            return;
        }

        try {
            $records = [];
            foreach ($this->buffer as $event) {
                $records[] = [
                    'event_type' => $event['event_type'],
                    'phone_number' => $event['phone_number'] ?? null,
                    'session_id' => $event['session_id'] ?? null,
                    'data' => json_encode($event),
                    'timestamp' => $event['timestamp'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            DB::table($this->tableName)->insert($records);

            Log::info('FlushAnalyticsBuffer: Async buffer flushed', [
                'records_count' => count($records),
            ]);
        } catch (\Exception $e) {
            Log::error('FlushAnalyticsBuffer: Failed to flush buffer asynchronously', [
                'error' => $e->getMessage(),
                'buffer_size' => count($this->buffer),
            ]);
        }
    }
}
