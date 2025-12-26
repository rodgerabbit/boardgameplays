<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SyncBoardGameFromBoardGameGeekJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Command to sync board games from BoardGameGeek using a CSV file.
 *
 * This command reads BoardGameGeek IDs from a CSV file and queues jobs
 * to sync each board game. The CSV file should have IDs in the first column.
 */
class SyncBoardGamesFromBoardGameGeekCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'boardgamegeek:sync-from-csv 
                            {file : The path to the CSV file containing BGG IDs}
                            {--limit= : Limit the number of games to sync}
                            {--delay=2 : Delay in seconds between queuing jobs}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync board games from BoardGameGeek using a CSV file with BGG IDs';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $filePath = $this->argument('file');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $delay = (int) $this->option('delay');

        if (!File::exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return self::FAILURE;
        }

        $this->info("Reading BGG IDs from: {$filePath}");

        $bggIds = $this->readBggIdsFromCsv($filePath, $limit);

        if (empty($bggIds)) {
            $this->warn('No BGG IDs found in the CSV file.');
            return self::FAILURE;
        }

        $this->info("Found " . count($bggIds) . " BGG IDs to sync.");

        if ($limit !== null && count($bggIds) > $limit) {
            $bggIds = array_slice($bggIds, 0, $limit);
            $this->info("Limited to {$limit} IDs.");
        }

        $this->info("Queueing sync jobs with {$delay} second delay between jobs...");

        $queued = 0;
        $skipped = 0;

        foreach ($bggIds as $index => $bggId) {
            $bggId = trim($bggId);

            if (empty($bggId) || !is_numeric($bggId)) {
                $skipped++;
                continue;
            }

            // Queue the job with increasing delay to respect rate limits
            SyncBoardGameFromBoardGameGeekJob::dispatch($bggId)
                ->delay(now()->addSeconds($delay * ($index + 1)));

            $queued++;

            if (($index + 1) % 10 === 0) {
                $this->info("Queued {$queued} jobs...");
            }
        }

        $this->info("Successfully queued {$queued} sync jobs.");
        if ($skipped > 0) {
            $this->warn("Skipped {$skipped} invalid IDs.");
        }

        Log::info('BoardGameGeek sync command completed', [
            'file' => $filePath,
            'queued' => $queued,
            'skipped' => $skipped,
        ]);

        return self::SUCCESS;
    }

    /**
     * Read BGG IDs from the CSV file.
     *
     * @param string $filePath The path to the CSV file
     * @param int|null $limit Optional limit on number of IDs to read
     * @return array<string> Array of BGG IDs
     */
    private function readBggIdsFromCsv(string $filePath, ?int $limit = null): array
    {
        $bggIds = [];
        $handle = fopen($filePath, 'r');

        if ($handle === false) {
            return [];
        }

        // Skip the header row
        $header = fgetcsv($handle, 0, ',', '"', '\\');
        if ($header === false) {
            fclose($handle);
            return [];
        }

        $count = 0;
        while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            if ($limit !== null && $count >= $limit) {
                break;
            }

            // Get the first column (index 0) which contains the BGG ID
            if (isset($row[0]) && !empty(trim($row[0]))) {
                $bggIds[] = trim($row[0]);
                $count++;
            }
        }

        fclose($handle);

        return $bggIds;
    }
}
