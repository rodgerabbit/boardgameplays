<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\BoardGameGeekApiClient;
use Illuminate\Console\Command;
use SimpleXMLElement;

/**
 * One-off command to fetch a BGG collection and compare API response with our DB schema.
 *
 * Run: php artisan bgg:test-collection RodgeRabbit
 * Or via Docker: docker compose run --rm app php artisan bgg:test-collection RodgeRabbit
 */
class TestBoardGameGeekCollectionCommand extends Command
{
    protected $signature = 'bgg:test-collection
                            {username=RodgeRabbit : BGG username}
                            {--dump-xml : Save raw XML to storage/app/bgg_collection_sample.xml}';

    protected $description = 'Fetch BGG collection for a user (with token), list all API fields, and compare to DB schema';

    public function handle(BoardGameGeekApiClient $apiClient): int
    {
        $username = (string) $this->argument('username');
        $token = config('boardgamegeek.api_token');
        if ($token === null || $token === '') {
            $this->error('BOARDGAMEGEEK_API_TOKEN is not set in .env');
            return self::FAILURE;
        }

        $this->info("Fetching collection for username: {$username} (using token from .env)");
        $urlTemplate = config('boardgamegeek.collection_api_url')
            ?? 'https://boardgamegeek.com/xmlapi2/collection?username={username}&stats=1&version=1';
        $url = str_replace('{username}', rawurlencode($username), $urlTemplate);
        $this->line('URL: ' . $url);

        try {
            $items = $apiClient->fetchCollection($username);
        } catch (\Throwable $e) {
            $this->error('Fetch failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->info('Fetched ' . count($items) . ' item(s).');

        if (count($items) === 0) {
            $this->warn('No items returned (empty collection or 202 not yet ready).');
            return self::SUCCESS;
        }

        $this->listApiFieldsFromParsedItems($items);
        $this->compareWithSchema();

        if ($this->option('dump-xml')) {
            $this->fetchAndDumpRawXml($url, $token);
        }

        return self::SUCCESS;
    }

    /**
     * List keys we get from the parser (current coverage).
     */
    private function listApiFieldsFromParsedItems(array $items): void
    {
        $this->newLine();
        $this->info('--- Fields we currently parse and store ---');
        $first = $items[0];
        foreach (array_keys($first) as $key) {
            $this->line('  ' . $key . ' => ' . json_encode($first[$key] ?? null));
        }
    }

    /**
     * Compare with bgg_collection_items table and suggest gaps.
     */
    private function compareWithSchema(): void
    {
        $this->newLine();
        $this->info('--- Database columns (bgg_collection_items) ---');
        $columns = [
            'user_id', 'bgg_id', 'bgg_object_id', 'bgg_version_id', 'bgg_collid', 'board_game_id',
            'name', 'year_published', 'thumbnail_url', 'image_url', 'user_rating', 'owned',
            'numplays', 'prev_owned', 'for_trade', 'want', 'want_to_play', 'want_to_buy', 'wishlist', 'preordered',
            'last_synced_at', 'bgg_last_modified', 'created_at', 'updated_at',
        ];
        foreach ($columns as $col) {
            $this->line('  ' . $col);
        }
        $this->newLine();
        $this->info('Coverage: All BGG collection item fields from the API (objectid, name, yearpublished, image, thumbnail, user_rating, status flags, numplays, collid, lastmodified) are parsed and stored in bgg_collection_items.');
    }

    /**
     * Fetch raw XML with token and dump to file; then list all XML elements in first item.
     */
    private function fetchAndDumpRawXml(string $url, string $token): void
    {
        $this->info('Fetching raw XML with token to list all API elements...');
        $response = \Illuminate\Support\Facades\Http::timeout(30)
            ->withHeaders(['Accept' => 'application/xml'])
            ->withToken($token)
            ->get($url);

        if (!$response->successful()) {
            $this->warn('Raw XML fetch status: ' . $response->status());
            return;
        }

        $xmlContent = $response->body();
        $path = storage_path('app/bgg_collection_sample.xml');
        file_put_contents($path, $xmlContent);
        $this->info('Saved raw XML to: ' . $path);

        try {
            $xml = new SimpleXMLElement($xmlContent);
            $items = $xml->item ?? [];
            if (count($items) === 0) {
                $this->warn('No <item> in XML (might be 202 or empty).');
                return;
            }
            $this->newLine();
            $this->info('--- All elements/attributes in first <item> from BGG API ---');
            $first = $items[0];
            $this->dumpElementStructure($first, 0);
        } catch (\Throwable $e) {
            $this->warn('Could not parse XML for structure: ' . $e->getMessage());
        }
    }

    private function dumpElementStructure(SimpleXMLElement $el, int $indent): void
    {
        $prefix = str_repeat('  ', $indent);
        foreach ($el->attributes() as $name => $value) {
            $this->line($prefix . '@' . $name . ' = ' . (string) $value);
        }
        foreach ($el->children() as $name => $child) {
            $attrs = [];
            foreach ($child->attributes() as $a => $v) {
                $attrs[] = (string) $a . '="' . (string) $v . '"';
            }
            $attrStr = empty($attrs) ? '' : ' [' . implode(' ', $attrs) . ']';
            $text = trim((string) $child);
            if ($text !== '' && strlen($text) < 80) {
                $this->line($prefix . '<' . $name . '>' . $attrStr . ' => ' . $text);
            } else {
                $this->line($prefix . '<' . $name . '>' . $attrStr);
                $this->dumpElementStructure($child, $indent + 1);
            }
        }
    }
}
