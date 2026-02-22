<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\BoardGamePlayDeduplicationService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Console\Command;

/**
 * Command to re-run play deduplication for a scope (all groups, a specific group, and optionally by date or date range).
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
                            {--group= : Only process plays in this group ID}
                            {--date= : Process only plays on this date (YYYY-MM-DD)}
                            {--from= : Start of date range (YYYY-MM-DD). Must be used with --to}
                            {--to= : End of date range (YYYY-MM-DD). Must be used with --from}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Re-run play deduplication (leading vs excluded) for all or a specific group, optionally limited by date or date range';

    /**
     * Execute the console command.
     */
    public function handle(BoardGamePlayDeduplicationService $deduplicationService): int
    {
        $groupId = $this->option('group') !== null
            ? (int) $this->option('group')
            : null;

        $dateOption = $this->option('date');
        $fromOption = $this->option('from');
        $toOption = $this->option('to');

        if ($dateOption !== null && ($fromOption !== null || $toOption !== null)) {
            $this->error('Use either --date or --from/--to, not both.');

            return self::FAILURE;
        }

        if ($fromOption !== null || $toOption !== null) {
            if ($fromOption === null || $toOption === null) {
                $this->error('Both --from and --to are required when using a date range.');

                return self::FAILURE;
            }

            try {
                $from = Carbon::parse($fromOption)->startOfDay();
                $to = Carbon::parse($toOption)->startOfDay();
            } catch (\Throwable $e) {
                $this->error('Invalid --from or --to date. Use YYYY-MM-DD.');

                return self::FAILURE;
            }

            if ($from->isAfter($to)) {
                $this->error('--from must be on or before --to.');

                return self::FAILURE;
            }
        }

        if ($dateOption !== null) {
            try {
                Carbon::parse($dateOption);
            } catch (\Throwable $e) {
                $this->error('Invalid --date. Use YYYY-MM-DD.');

                return self::FAILURE;
            }
        }

        $scopeDescription = $this->buildScopeDescription($groupId, $dateOption, $fromOption ?? null, $toOption ?? null);
        $this->info("Syncing play deduplication for {$scopeDescription}...");

        if ($dateOption !== null) {
            $playedAt = Carbon::parse($dateOption)->startOfDay();
            $deduplicationService->syncDeduplicationForGroup($groupId, null, $playedAt);
        } elseif ($fromOption !== null && $toOption !== null) {
            $period = CarbonPeriod::create($from, $to);
            foreach ($period as $date) {
                $deduplicationService->syncDeduplicationForGroup($groupId, null, $date);
            }
        } else {
            $deduplicationService->syncDeduplicationForGroup($groupId);
        }

        $this->info('Done.');

        return self::SUCCESS;
    }

    private function buildScopeDescription(?int $groupId, ?string $date, ?string $from, ?string $to): string
    {
        $parts = [];

        if ($groupId !== null) {
            $parts[] = "group {$groupId}";
        } else {
            $parts[] = 'all groups';
        }

        if ($date !== null) {
            $parts[] = "date {$date}";
        } elseif ($from !== null && $to !== null) {
            $parts[] = "dates {$from} to {$to}";
        }

        return implode(', ', $parts);
    }
}
