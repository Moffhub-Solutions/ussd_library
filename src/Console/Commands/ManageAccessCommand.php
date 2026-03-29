<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Console\Commands;

use Illuminate\Console\Command;
use Moffhub\Ussd\Models\UssdAccessList;

class ManageAccessCommand extends Command
{
    protected $signature = 'ussd:manage-access
                            {action : Action to perform: add, remove, or list}
                            {--type=whitelist : List type: whitelist or blacklist}
                            {--phone= : Phone number to add or remove}
                            {--reason= : Reason for the action}';

    protected $description = 'Manage USSD whitelist and blacklist entries';

    public function handle(): int
    {
        $action = $this->argument('action');
        $type = $this->option('type');

        if (! in_array($type, ['whitelist', 'blacklist'], true)) {
            $this->error('Invalid type. Use --type=whitelist or --type=blacklist.');

            return self::FAILURE;
        }

        return match ($action) {
            'add' => $this->addEntry($type),
            'remove' => $this->removeEntry($type),
            'list' => $this->listEntries($type),
            default => $this->invalidAction(),
        };
    }

    protected function addEntry(string $type): int
    {
        $phone = $this->option('phone');

        if ($phone === null || $phone === '') {
            $this->error('Phone number is required. Use --phone=+254...');

            return self::FAILURE;
        }

        $reason = $this->option('reason');

        if ($type === 'whitelist') {
            UssdAccessList::addToWhitelist($phone, $reason, 'artisan');
        } else {
            UssdAccessList::addToBlacklist($phone, $reason, 'artisan');
        }

        $this->info("Added {$phone} to {$type}.");

        return self::SUCCESS;
    }

    protected function removeEntry(string $type): int
    {
        $phone = $this->option('phone');

        if ($phone === null || $phone === '') {
            $this->error('Phone number is required. Use --phone=+254...');

            return self::FAILURE;
        }

        if ($type === 'whitelist') {
            UssdAccessList::removeFromWhitelist($phone);
        } else {
            UssdAccessList::removeFromBlacklist($phone);
        }

        $this->info("Removed {$phone} from {$type}.");

        return self::SUCCESS;
    }

    protected function listEntries(string $type): int
    {
        $entries = UssdAccessList::where('type', $type)
            ->where('is_active', true)
            ->get();

        if ($entries->isEmpty()) {
            $this->info("No active {$type} entries found.");

            return self::SUCCESS;
        }

        $rows = $entries->map(fn (UssdAccessList $entry) => [
            'phone' => $entry->phone_number,
            'reason' => $entry->reason ?? 'N/A',
            'added_by' => $entry->added_by ?? 'N/A',
            'expires_at' => $entry->expires_at?->toDateTimeString() ?? 'Never',
            'created_at' => $entry->created_at->toDateTimeString(),
        ])->toArray();

        $this->table(
            ['Phone', 'Reason', 'Added By', 'Expires At', 'Created At'],
            $rows
        );

        $this->info("Total {$type} entries: {$entries->count()}");

        return self::SUCCESS;
    }

    protected function invalidAction(): int
    {
        $this->error('Invalid action. Use add, remove, or list.');

        return self::FAILURE;
    }
}
