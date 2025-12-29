<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Group;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Command to hard delete groups that have been soft-deleted for more than the retention period.
 *
 * This command runs daily via the Laravel scheduler and permanently deletes groups
 * that were soft-deleted more than the configured retention period ago (default: 12 months).
 */
class HardDeleteExpiredGroupsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'groups:hard-delete-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Hard delete groups that have been soft-deleted for more than the retention period';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $retentionMonths = config('groups.hard_delete_retention_months', 12);
        $cutoffDate = now()->subMonths($retentionMonths);

        $this->info("Looking for groups soft-deleted before {$cutoffDate->toDateString()}...");

        $expiredGroups = Group::onlyTrashed()
            ->where('deleted_at', '<', $cutoffDate)
            ->get();

        if ($expiredGroups->isEmpty()) {
            $this->info('No expired groups found.');
            return self::SUCCESS;
        }

        $count = $expiredGroups->count();
        $this->info("Found {$count} expired group(s) to permanently delete.");

        $deletedCount = 0;
        foreach ($expiredGroups as $group) {
            try {
                $groupId = $group->id;
                $groupName = $group->friendly_name;

                // Force delete (hard delete)
                $group->forceDelete();

                $deletedCount++;
                $this->line("Deleted group #{$groupId}: {$groupName}");

                Log::info("Hard deleted expired group", [
                    'group_id' => $groupId,
                    'group_name' => $groupName,
                    'deleted_at' => $group->deleted_at?->toIso8601String(),
                ]);
            } catch (\Exception $e) {
                $this->error("Failed to delete group #{$group->id}: {$e->getMessage()}");
                Log::error("Failed to hard delete expired group", [
                    'group_id' => $group->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Successfully deleted {$deletedCount} of {$count} expired group(s).");

        return self::SUCCESS;
    }
}
