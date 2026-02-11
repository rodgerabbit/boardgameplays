<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\BoardGamePlayDeduplicationService;
use Illuminate\Console\Command;

/**
 * Command to re-run play deduplication for a scope (all groups, or a specific group).
 *
 * Use this to fix or refresh which play is "leading" when duplicate detection
 * or leading-play selection logic has changed.
 */
class SyncPlayDeduplicationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'plays:sync-deduplication
                            {--group= : Only process plays in this group ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Re-run play deduplication (leading vs excluded) for all or a specific group';

    /**
     * Execute the console command.
     */
    public function handle(BoardGamePlayDeduplicationService $deduplicationService): int
    {
        $groupId = $this->option('group') !== null
            ? (int) $this->option('group')
            : null;

        if ($groupId !== null) {
            $this->info("Syncing play deduplication for group {$groupId}...");
            $deduplicationService->syncDeduplicationForGroup($groupId);
            $this->info('Done.');
        } else {
            $this->info('Syncing play deduplication for all groups...');
            $deduplicationService->syncDeduplicationForGroup(null);
            $this->info('Done.');
        }

        return self::SUCCESS;
    }
}
