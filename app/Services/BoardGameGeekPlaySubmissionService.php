<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BoardGamePlay;
use App\Models\BoardGamePlayPlayer;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service class for submitting plays to BoardGameGeek.com.
 *
 * This service handles authentication with BGG and submission of plays
 * using the BGG API endpoints.
 */
class BoardGameGeekPlaySubmissionService extends BaseService
{
    private const LOGIN_URL = 'https://boardgamegeek.com/login/api/v1';
    private const PLAY_SUBMISSION_URL = 'https://boardgamegeek.com/geekplay.php';
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    /**
     * Login to BoardGameGeek and return session data.
     *
     * @param string $username The BGG username
     * @param string $password The BGG password
     * @return array<string, mixed> Session/cookie data
     * @throws \RuntimeException If login fails
     */
    public function loginToBoardGameGeek(string $username, string $password): array
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => self::USER_AGENT,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
                ->timeout(30)
                ->post(self::LOGIN_URL, [
                    'credentials' => [
                        'username' => $username,
                        'password' => $password,
                    ],
                ]);

            if (!$response->successful()) {
                throw new \RuntimeException('BGG login failed with status: ' . $response->status());
            }

            // Extract cookies from response
            $cookies = [];
            foreach ($response->cookies() as $cookie) {
                $cookies[$cookie->getName()] = $cookie->getValue();
            }

            return [
                'cookies' => $cookies,
                'headers' => $response->headers(),
            ];
        } catch (\Exception $e) {
            Log::error('BGG login failed', [
                'username' => $username,
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Failed to login to BoardGameGeek: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Submit a play to BoardGameGeek.
     *
     * @param BoardGamePlay $play The play to submit
     * @param array<string, mixed> $credentials Session credentials from login
     * @param string|null $bggPlayId Optional existing BGG play ID for updates
     * @return array<string, mixed> Response data including playid
     * @throws \RuntimeException If submission fails
     */
    public function submitPlayToBoardGameGeek(
        BoardGamePlay $play,
        array $credentials,
        ?string $bggPlayId = null
    ): array {
        try {
            $playData = $this->mapPlayToBoardGameGeekFormat($play, $bggPlayId);

            // Build cookies string for the request
            $cookieString = '';
            if (isset($credentials['cookies'])) {
                foreach ($credentials['cookies'] as $name => $value) {
                    $cookieString .= $name . '=' . $value . '; ';
                }
            }

            $response = Http::withHeaders([
                'User-Agent' => self::USER_AGENT,
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Cookie' => rtrim($cookieString, '; '),
                'Referer' => self::PLAY_SUBMISSION_URL,
            ])
                ->timeout(30)
                ->asForm()
                ->post(self::PLAY_SUBMISSION_URL, $playData);

            if (!$response->successful()) {
                throw new \RuntimeException('BGG play submission failed with status: ' . $response->status());
            }

            $jsonResponse = $response->json();

            if (!isset($jsonResponse['playid'])) {
                throw new \RuntimeException('BGG play submission did not return a playid');
            }

            return $jsonResponse;
        } catch (\Exception $e) {
            Log::error('BGG play submission failed', [
                'play_id' => $play->id,
                'bgg_play_id' => $bggPlayId,
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Failed to submit play to BoardGameGeek: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Map play data to BGG API format.
     *
     * @param BoardGamePlay $play The play to map
     * @param string|null $bggPlayId Optional existing BGG play ID
     * @return array<string, mixed> Form data for BGG API
     */
    public function mapPlayToBoardGameGeekFormat(BoardGamePlay $play, ?string $bggPlayId = null): array
    {
        $playData = [
            'ajax' => '1',
            'action' => 'save',
            'objecttype' => 'thing',
            'objectid' => $play->boardGame->bgg_id ?? '',
            'playdate' => $play->played_at->format('Y-m-d'),
            'location' => $play->location,
            'quantity' => '1',
            'comments' => $play->comment ?? '',
            'length' => $play->game_length_minutes ?? '',
        ];

        if ($bggPlayId !== null) {
            $playData['playid'] = $bggPlayId;
        }

        // Map players
        $index = 0;
        foreach ($play->players as $player) {
            $playerData = $this->mapPlayerToBoardGameGeekFormat($player, $index);
            $playData = array_merge($playData, $playerData);
            $index++;
        }

        return $playData;
    }

    /**
     * Map player data to BGG format.
     *
     * @param BoardGamePlayPlayer $player The player to map
     * @param int $index The player index
     * @return array<string, mixed> Player data in BGG format
     */
    public function mapPlayerToBoardGameGeekFormat(BoardGamePlayPlayer $player, int $index): array
    {
        $playerData = [];

        // Determine name and username
        if ($player->isUserPlayer() && $player->user !== null) {
            // Use BGG username if available, otherwise use user name
            $bggUsername = $player->user->board_game_geek_username ?? null;
            if ($bggUsername !== null) {
                $playerData["players[{$index}][username]"] = $bggUsername;
                $playerData["players[{$index}][name]"] = '';
            } else {
                $playerData["players[{$index}][name]"] = $player->user->name;
                $playerData["players[{$index}][username]"] = '';
            }
        } elseif ($player->isBggPlayer()) {
            $playerData["players[{$index}][username]"] = $player->board_game_geek_username;
            $playerData["players[{$index}][name]"] = '';
        } elseif ($player->isGuestPlayer()) {
            $playerData["players[{$index}][name]"] = $player->guest_name;
            $playerData["players[{$index}][username]"] = '';
        }

        // Map other fields
        if ($player->score !== null) {
            $playerData["players[{$index}][score]"] = (string) $player->score;
        }

        $playerData["players[{$index}][new]"] = $player->is_new_player ? '1' : '0';
        $playerData["players[{$index}][win]"] = $player->is_winner ? '1' : '0';

        return $playerData;
    }

    /**
     * Get BGG credentials for a play using three methods.
     *
     * @param BoardGamePlay $play The play
     * @param string|null $providedUsername Optional provided username
     * @param string|null $providedPassword Optional provided password
     * @return array<string, string> Credentials array with 'username' and 'password'
     * @throws \RuntimeException If no credentials are available
     */
    public function getBggCredentialsForPlay(
        BoardGamePlay $play,
        ?string $providedUsername = null,
        ?string $providedPassword = null
    ): array {
        // Method 3: Provided credentials (highest priority)
        if ($providedUsername !== null && $providedPassword !== null) {
            return [
                'username' => $providedUsername,
                'password' => $providedPassword,
            ];
        }

        // Method 2: User's stored credentials
        $user = $play->creator;
        if ($user->sync_plays_to_board_game_geek
            && $user->board_game_geek_username !== null
            && $user->board_game_geek_password_encrypted !== null
        ) {
            return [
                'username' => $user->board_game_geek_username,
                'password' => Crypt::decryptString($user->board_game_geek_password_encrypted),
            ];
        }

        // Method 1: Generic credentials from config
        $genericUsername = config('boardgamegeek.generic_username');
        $genericPassword = config('boardgamegeek.generic_password');

        if ($genericUsername !== null && $genericPassword !== null) {
            return [
                'username' => $genericUsername,
                'password' => $genericPassword,
            ];
        }

        throw new \RuntimeException('No BGG credentials available for play submission');
    }

    /**
     * Handle BGG submission error.
     *
     * @param \Exception $exception The exception that occurred
     * @param BoardGamePlay $play The play that failed
     * @return void
     */
    public function handleBggSubmissionError(\Exception $exception, BoardGamePlay $play): void
    {
        $play->update([
            'bgg_sync_to_status' => 'failed',
            'bgg_sync_to_error_message' => $exception->getMessage(),
        ]);

        Log::error('BGG play submission failed', [
            'play_id' => $play->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}

