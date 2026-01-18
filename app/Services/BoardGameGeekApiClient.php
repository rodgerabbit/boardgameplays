<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\BoardGameGeekGameDto;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use SimpleXMLElement;

/**
 * Service class for interacting with the BoardGameGeek XML API.
 *
 * This service handles all communication with BoardGameGeek.com's XML API,
 * including rate limiting, retry logic, error handling, and XML parsing.
 * All API calls are designed to be used within background jobs only.
 */
class BoardGameGeekApiClient extends BaseService
{
    private const CACHE_LOCK_KEY = 'boardgamegeek_api_request_lock';
    private const CACHE_LAST_REQUEST_TIME_KEY = 'boardgamegeek_api_last_request_time';
    private const LOCK_TIMEOUT_SECONDS = 300; // 5 minutes max lock time

    /**
     * Create a new BoardGameGeekApiClient instance.
     */
    public function __construct(
        private readonly string $apiBaseUrl,
        private readonly ?string $apiToken,
        private readonly int $minimumSecondsBetweenRequests,
        private readonly int $maxIdsPerRequest,
        private readonly int $maxRetryAttempts,
        private readonly int $retryAfter202Seconds,
        private readonly int $exponentialBackoffMaxSeconds,
    ) {
    }

    /**
     * Fetch board games from BoardGameGeek API by their IDs.
     *
     * This method handles rate limiting, retries, and error handling.
     * Only one request can be processed at a time to respect rate limits.
     *
     * @param array<int|string> $bggIds Array of BoardGameGeek IDs (max 20 per request)
     * @return array<BoardGameGeekGameDto> Array of board game DTOs
     * @throws \RuntimeException If the API request fails after all retries
     * @throws \InvalidArgumentException If too many IDs are provided
     */
    public function fetchBoardGamesByIds(array $bggIds): array
    {
        if (count($bggIds) > $this->maxIdsPerRequest) {
            throw new \InvalidArgumentException(
                "Maximum {$this->maxIdsPerRequest} IDs allowed per request. Provided: " . count($bggIds)
            );
        }

        if (empty($bggIds)) {
            return [];
        }

        // Ensure only one request runs at a time
        $lock = Cache::lock(self::CACHE_LOCK_KEY, self::LOCK_TIMEOUT_SECONDS);

        try {
            $lock->block(300); // Wait up to 5 minutes for the lock

            // Enforce minimum time between requests
            $this->enforceRateLimit();

            $idsString = implode(',', array_map('strval', $bggIds));
            $url = "{$this->apiBaseUrl}/thing?id={$idsString}&stats=1&type=boardgame,boardgameexpansion";

            $attempt = 0;
            $lastException = null;

            while ($attempt < $this->maxRetryAttempts) {
                try {
                    $response = $this->makeHttpRequest($url);

                    if ($response->status() === 200) {
                        $this->updateLastRequestTime();
                        return $this->parseXmlResponse($response->body());
                    }

                    if ($response->status() === 202) {
                        // Accepted but processing - retry after delay
                        $attempt++;
                        if ($attempt < $this->maxRetryAttempts) {
                            Log::info('BoardGameGeek API returned 202, retrying after delay', [
                                'attempt' => $attempt,
                                'bgg_ids' => $bggIds,
                            ]);
                            sleep($this->retryAfter202Seconds);
                            continue;
                        }
                    }

                    if ($response->status() === 429) {
                        // Rate limited - exponential backoff
                        $attempt++;
                        if ($attempt < $this->maxRetryAttempts) {
                            $backoffSeconds = min(
                                pow(2, $attempt) + rand(0, 3), // Add some jitter
                                $this->exponentialBackoffMaxSeconds
                            );
                            Log::warning('BoardGameGeek API rate limited, backing off', [
                                'attempt' => $attempt,
                                'backoff_seconds' => $backoffSeconds,
                                'bgg_ids' => $bggIds,
                            ]);
                            sleep((int) $backoffSeconds);
                            continue;
                        }
                    }

                    if ($response->status() === 401) {
                        // Unauthorized - token issue
                        $errorMessage = 'BoardGameGeek API token was not accepted (401 Unauthorized)';
                        Log::error($errorMessage, [
                            'bgg_ids' => $bggIds,
                            'status' => $response->status(),
                        ]);
                        throw new \RuntimeException($errorMessage);
                    }

                    // Other error status
                    $errorMessage = "BoardGameGeek API returned status {$response->status()}";
                    Log::error($errorMessage, [
                        'bgg_ids' => $bggIds,
                        'status' => $response->status(),
                        'response_body' => substr($response->body(), 0, 500),
                    ]);

                    $attempt++;
                    if ($attempt < $this->maxRetryAttempts) {
                        sleep($this->retryAfter202Seconds);
                        continue;
                    }

                    // All retries exhausted - throw final exception
                    $finalErrorMessage = "Failed to fetch board games from BoardGameGeek after {$this->maxRetryAttempts} attempts. Last error: {$errorMessage}";
                    throw new \RuntimeException($finalErrorMessage);
                } catch (RequestException $e) {
                    $lastException = $e;
                    $attempt++;

                    if ($attempt < $this->maxRetryAttempts) {
                        $backoffSeconds = min(
                            pow(2, $attempt) + rand(0, 3),
                            $this->exponentialBackoffMaxSeconds
                        );
                        Log::warning('BoardGameGeek API request exception, retrying', [
                            'attempt' => $attempt,
                            'backoff_seconds' => $backoffSeconds,
                            'error' => $e->getMessage(),
                            'bgg_ids' => $bggIds,
                        ]);
                        sleep((int) $backoffSeconds);
                        continue;
                    }
                }
            }

            // All retries exhausted
            $errorMessage = "Failed to fetch board games from BoardGameGeek after {$this->maxRetryAttempts} attempts";
            Log::error($errorMessage, [
                'bgg_ids' => $bggIds,
                'last_exception' => $lastException?->getMessage(),
            ]);

            throw new \RuntimeException($errorMessage, 0, $lastException);
        } finally {
            $lock->release();
        }
    }

    /**
     * Make an HTTP request to the BoardGameGeek API.
     *
     * @param string $url The full URL to request
     * @return \Illuminate\Http\Client\Response
     */
    private function makeHttpRequest(string $url): \Illuminate\Http\Client\Response
    {
        $request = Http::timeout(30)
            ->retry(0) // We handle retries manually
            ->withHeaders([
                'Accept' => 'application/xml',
            ]);

        if ($this->apiToken !== null) {
            $request->withToken($this->apiToken);
        }

        return $request->get($url);
    }

    /**
     * Enforce the minimum time between requests.
     *
     * @return void
     */
    private function enforceRateLimit(): void
    {
        $lastRequestTime = Cache::get(self::CACHE_LAST_REQUEST_TIME_KEY);

        if ($lastRequestTime !== null) {
            // Calculate seconds since last request (handle both past and future timestamps)
            $now = now();
            $secondsSinceLastRequest = $now->diffInSeconds($lastRequestTime, false);
            
            // If last request was in the future (clock skew), treat as 0 seconds
            if ($secondsSinceLastRequest < 0) {
                $secondsSinceLastRequest = 0;
            }

            if ($secondsSinceLastRequest < $this->minimumSecondsBetweenRequests) {
                $waitSeconds = $this->minimumSecondsBetweenRequests - $secondsSinceLastRequest;
                
                // Cap the wait time to prevent excessive delays (max 60 seconds)
                $maxWaitSeconds = 60;
                $waitSeconds = min($waitSeconds, $maxWaitSeconds);
                
                // Ensure wait time is not negative
                $waitSeconds = max(0, $waitSeconds);
                
                if ($waitSeconds > 0) {
                    Log::debug('Rate limiting: waiting before next BoardGameGeek API request', [
                        'wait_seconds' => $waitSeconds,
                        'seconds_since_last_request' => $secondsSinceLastRequest,
                        'minimum_seconds_between_requests' => $this->minimumSecondsBetweenRequests,
                    ]);
                    sleep((int) $waitSeconds);
                }
            }
        }
    }

    /**
     * Update the timestamp of the last API request.
     *
     * @return void
     */
    private function updateLastRequestTime(): void
    {
        Cache::put(self::CACHE_LAST_REQUEST_TIME_KEY, now(), now()->addHours(1));
    }

    /**
     * Parse the XML response from BoardGameGeek API.
     *
     * @param string $xmlContent The XML content to parse
     * @return array<BoardGameGeekGameDto>
     */
    private function parseXmlResponse(string $xmlContent): array
    {
        try {
            $xml = new SimpleXMLElement($xmlContent);
            $games = [];

            foreach ($xml->item as $item) {
                $gameDto = $this->parseGameItem($item);
                if ($gameDto !== null) {
                    $games[] = $gameDto;
                }
            }

            return $games;
        } catch (\Exception $e) {
            Log::error('Failed to parse BoardGameGeek XML response', [
                'error' => $e->getMessage(),
                'xml_preview' => substr($xmlContent, 0, 500),
            ]);
            throw new \RuntimeException('Failed to parse BoardGameGeek XML response: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Parse a single game item from the XML.
     *
     * @param SimpleXMLElement $item The XML item element
     * @return BoardGameGeekGameDto|null
     */
    private function parseGameItem(SimpleXMLElement $item): ?BoardGameGeekGameDto
    {
        try {
            $bggId = (string) $item['id'];
            $name = $this->extractPrimaryName($item);
            $description = $this->extractDescription($item);
            $minPlayers = $this->extractIntegerValue($item, 'minplayers');
            $maxPlayers = $this->extractIntegerValue($item, 'maxplayers');
            $playingTimeMinutes = $this->extractIntegerValue($item, 'playingtime');
            $yearPublished = $this->extractIntegerValue($item, 'yearpublished');

            $publisher = $this->extractLinkValue($item, 'boardgamepublisher');
            $designer = $this->extractLinkValue($item, 'boardgamedesigner');

            $imageUrl = $this->extractStringValue($item, 'image');
            $thumbnailUrl = $this->extractStringValue($item, 'thumbnail');

            $bggRating = $this->extractRating($item);
            $complexityRating = $this->extractComplexity($item);

            $isExpansion = $this->isExpansion($item);

            return new BoardGameGeekGameDto(
                bggId: $bggId,
                name: $name,
                description: $description,
                minPlayers: $minPlayers,
                maxPlayers: $maxPlayers,
                playingTimeMinutes: $playingTimeMinutes,
                yearPublished: $yearPublished,
                publisher: $publisher,
                designer: $designer,
                imageUrl: $imageUrl,
                thumbnailUrl: $thumbnailUrl,
                bggRating: $bggRating,
                complexityRating: $complexityRating,
                isExpansion: $isExpansion,
            );
        } catch (\Exception $e) {
            Log::warning('Failed to parse individual game item from BoardGameGeek XML', [
                'error' => $e->getMessage(),
                'item_id' => (string) ($item['id'] ?? 'unknown'),
            ]);
            return null;
        }
    }

    /**
     * Extract the primary name from the item.
     *
     * @param SimpleXMLElement $item
     * @return string
     */
    private function extractPrimaryName(SimpleXMLElement $item): string
    {
        foreach ($item->name as $name) {
            $type = (string) $name['type'];
            if ($type === 'primary' || $type === '') {
                return (string) $name['value'];
            }
        }

        // Fallback to first name if no primary found
        if (isset($item->name[0])) {
            return (string) $item->name[0]['value'];
        }

        return 'Unknown Game';
    }

    /**
     * Extract description from the item.
     *
     * @param SimpleXMLElement $item
     * @return string|null
     */
    private function extractDescription(SimpleXMLElement $item): ?string
    {
        $description = $this->extractStringValue($item, 'description');
        if ($description !== null) {
            // Clean up HTML entities and tags
            $description = html_entity_decode($description, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $description = strip_tags($description);
            $description = trim($description);
            return $description !== '' ? $description : null;
        }

        return null;
    }

    /**
     * Extract an integer value from the item.
     *
     * In BGG XML, integer values like playingtime, yearpublished, minplayers, maxplayers
     * are stored as child elements with a 'value' attribute (e.g., <playingtime value="60"/>).
     *
     * @param SimpleXMLElement $item
     * @param string $attributeName
     * @return int|null
     */
    private function extractIntegerValue(SimpleXMLElement $item, string $attributeName): ?int
    {
        // Check if the child element exists
        if (isset($item->$attributeName)) {
            $element = $item->$attributeName;
            // Check if it has a 'value' attribute (BGG XML format)
            if (isset($element['value'])) {
                $value = (string) $element['value'];
                if ($value !== '') {
                    $intValue = (int) $value;
                    return $intValue > 0 ? $intValue : null;
                }
            }
            // Fallback: try to get text content if no value attribute
            $value = (string) $element;
            if ($value !== '') {
                $intValue = (int) $value;
                return $intValue > 0 ? $intValue : null;
            }
        }

        return null;
    }

    /**
     * Extract a string value from the item.
     *
     * @param SimpleXMLElement $item
     * @param string $attributeName
     * @return string|null
     */
    private function extractStringValue(SimpleXMLElement $item, string $attributeName): ?string
    {
        if (isset($item->$attributeName)) {
            $value = (string) $item->$attributeName;
            return $value !== '' ? $value : null;
        }

        return null;
    }

    /**
     * Extract a link value (e.g., publisher, designer) from the item.
     *
     * @param SimpleXMLElement $item
     * @param string $linkType
     * @return string|null
     */
    private function extractLinkValue(SimpleXMLElement $item, string $linkType): ?string
    {
        if (isset($item->link)) {
            foreach ($item->link as $link) {
                $type = (string) $link['type'];
                if ($type === $linkType) {
                    return (string) $link['value'];
                }
            }
        }

        return null;
    }

    /**
     * Extract the BGG rating from the item statistics.
     *
     * @param SimpleXMLElement $item
     * @return float|null
     */
    private function extractRating(SimpleXMLElement $item): ?float
    {
        if (isset($item->statistics->ratings->average)) {
            $value = (string) $item->statistics->ratings->average['value'];
            $floatValue = (float) $value;
            return $floatValue > 0 ? round($floatValue, 3) : null;
        }

        return null;
    }

    /**
     * Extract the complexity rating from the item statistics.
     *
     * @param SimpleXMLElement $item
     * @return float|null
     */
    private function extractComplexity(SimpleXMLElement $item): ?float
    {
        if (isset($item->statistics->ratings->averageweight)) {
            $value = (string) $item->statistics->ratings->averageweight['value'];
            $floatValue = (float) $value;
            return $floatValue > 0 ? round($floatValue, 3) : null;
        }

        return null;
    }

    /**
     * Determine if the item is an expansion.
     *
     * @param SimpleXMLElement $item
     * @return bool
     */
    private function isExpansion(SimpleXMLElement $item): bool
    {
        // Check if the item has a link to a base game
        if (isset($item->link)) {
            foreach ($item->link as $link) {
                $type = (string) $link['type'];
                if ($type === 'boardgameexpansion') {
                    return false; // This is a base game that has expansions
                }
                if ($type === 'boardgame') {
                    // This might be an expansion linking to its base game
                    // We need to check the item's type attribute
                    break;
                }
            }
        }

        // Check the type attribute directly
        $type = (string) ($item['type'] ?? '');
        return $type === 'boardgameexpansion';
    }
}

