<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\BoardGame;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/**
 * BoardGameSeeder for populating the database with board game test data.
 *
 * This seeder creates sample board games for development and testing purposes.
 * It attempts to read from a CSV file if available, otherwise falls back to factory-generated data.
 */
class BoardGameSeeder extends Seeder
{
    /**
     * Path to the CSV file containing board game data.
     */
    private const CSV_FILE_PATH = 'storage/boardgamegeek/boardgames_ranks.csv';

    /**
     * Maximum number of board games to seed from CSV.
     */
    private const MAX_CSV_RECORDS = 1000;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $csvPath = base_path(self::CSV_FILE_PATH);

        if (File::exists($csvPath)) {
            $this->seedFromCsv($csvPath);
        } else {
            $this->seedFromFactory();
        }
    }

    /**
     * Seed board games from CSV file.
     *
     * @param string $csvPath The full path to the CSV file
     */
    private function seedFromCsv(string $csvPath): void
    {
        $this->command->info('Reading board games from CSV file...');

        $handle = fopen($csvPath, 'r');
        if ($handle === false) {
            $this->command->error('Failed to open CSV file: ' . $csvPath);
            $this->seedFromFactory();
            return;
        }

        // Read and skip header row
        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            $this->command->error('CSV file is empty or invalid');
            $this->seedFromFactory();
            return;
        }

        $processedCount = 0;
        $skippedCount = 0;
        $batchSize = 500;
        $batch = [];

        $this->command->info("Limiting to first " . self::MAX_CSV_RECORDS . " board games from CSV...");

        while (($row = fgetcsv($handle)) !== false) {
            // Stop if we've reached the maximum number of records
            if ($processedCount >= self::MAX_CSV_RECORDS) {
                break;
            }

            if (count($row) < count($header)) {
                $skippedCount++;
                continue;
            }

            $data = array_combine($header, $row);
            if ($data === false) {
                $skippedCount++;
                continue;
            }

            // Map CSV columns to model attributes
            $boardGameData = $this->mapCsvRowToBoardGameData($data);
            if ($boardGameData === null) {
                $skippedCount++;
                continue;
            }

            // Check if adding this item would exceed the limit
            $totalAfterThis = $processedCount + count($batch) + 1;
            if ($totalAfterThis > self::MAX_CSV_RECORDS) {
                break;
            }

            $batch[] = $boardGameData;

            // Insert in batches for better performance
            if (count($batch) >= $batchSize) {
                $this->insertBatch($batch);
                $processedCount += count($batch);
                $batch = [];
                $this->command->info("Processed {$processedCount} board games...");
            }
        }

        // Insert remaining items
        if (count($batch) > 0) {
            $this->insertBatch($batch);
            $processedCount += count($batch);
        }

        fclose($handle);

        $this->command->info("Successfully seeded {$processedCount} board games from CSV");
        if ($processedCount >= self::MAX_CSV_RECORDS) {
            $this->command->info("Reached the limit of " . self::MAX_CSV_RECORDS . " board games");
        }
        if ($skippedCount > 0) {
            $this->command->warn("Skipped {$skippedCount} invalid rows");
        }
    }

    /**
     * Map CSV row data to board game model attributes.
     *
     * @param array<string, string> $csvRow The CSV row data
     * @return array<string, mixed>|null The mapped data or null if invalid
     */
    private function mapCsvRowToBoardGameData(array $csvRow): ?array
    {
        // Skip if required fields are missing
        if (empty($csvRow['id']) || empty($csvRow['name'])) {
            return null;
        }

        $bggId = trim($csvRow['id']);
        $name = trim($csvRow['name']);

        // Skip if bgg_id already exists (avoid duplicates)
        if (BoardGame::where('bgg_id', $bggId)->exists()) {
            return null;
        }

        $data = [
            'name' => $name,
            'bgg_id' => $bggId,
            'is_expansion' => isset($csvRow['is_expansion']) && trim($csvRow['is_expansion']) === '1',
        ];

        // Map optional fields
        if (!empty($csvRow['yearpublished'])) {
            $yearPublished = (int) trim($csvRow['yearpublished']);
            if ($yearPublished > 0) {
                $data['year_published'] = $yearPublished;
            }
        }

        if (!empty($csvRow['average'])) {
            $average = (float) trim($csvRow['average']);
            if ($average >= 0 && $average <= 10) {
                $data['bgg_rating'] = round($average, 3);
            }
        }

        // Set default values for required fields
        $data['min_players'] = 1;
        $data['max_players'] = 4;

        return $data;
    }

    /**
     * Insert a batch of board games efficiently.
     *
     * @param array<int, array<string, mixed>> $batch The batch of board game data
     */
    private function insertBatch(array $batch): void
    {
        // Use insertOrIgnore to avoid duplicates on bgg_id
        foreach ($batch as $data) {
            BoardGame::updateOrCreate(
                ['bgg_id' => $data['bgg_id']],
                $data
            );
        }
    }

    /**
     * Seed board games using factory (fallback method).
     */
    private function seedFromFactory(): void
    {
        $this->command->info('CSV file not found, using factory-generated data...');

        // Create 50 random board games using the factory
        BoardGame::factory()->count(50)->create();

        // Create some well-known board games with specific attributes
        BoardGame::factory()->create([
            'name' => 'Catan',
            'description' => 'Players try to be the dominant force on the island of Catan by building settlements, cities, and roads.',
            'min_players' => 3,
            'max_players' => 4,
            'playing_time_minutes' => 90,
            'year_published' => 1995,
            'publisher' => 'Catan Studio',
            'designer' => 'Klaus Teuber',
            'bgg_id' => '13',
        ]);

        BoardGame::factory()->create([
            'name' => 'Ticket to Ride',
            'description' => 'Build your train routes across North America in this award-winning game.',
            'min_players' => 2,
            'max_players' => 5,
            'playing_time_minutes' => 60,
            'year_published' => 2004,
            'publisher' => 'Days of Wonder',
            'designer' => 'Alan R. Moon',
            'bgg_id' => '9209',
        ]);

        BoardGame::factory()->create([
            'name' => 'Wingspan',
            'description' => 'Attract a beautiful and diverse collection of birds to your wildlife preserve.',
            'min_players' => 1,
            'max_players' => 5,
            'playing_time_minutes' => 70,
            'year_published' => 2019,
            'publisher' => 'Stonemaier Games',
            'designer' => 'Elizabeth Hargrave',
            'bgg_id' => '266524',
        ]);

        $this->command->info('Factory-generated board games created');
    }
}





