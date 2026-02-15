<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\BoardGame;
use App\Models\User;
use App\Services\BoardGameGeekPlaySyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job for importing a single board game play from BoardGameGeek after the board game has been synced.
 *
 * This job runs after SyncBoardGameFromBoardGameGeekJob and creates the play record using
 * the serialized play payload. Used when a play references a board game that was not yet in the database.
 */
class ImportBoardGamePlayFromBoardGameGeekJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public int $tries = 5;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public int $backoff = 10;

    /**
     * Create a new job instance.
     *
     * @param int $userId The user ID who owns the play
     * @param array<string, mixed> $playPayload Serialized play data (from BoardGameGeekPlaySyncService::buildPlayPayloadFromBggXml)
     */
    public function __construct(
        public readonly int $userId,
        public readonly array $playPayload,
    ) {
    }

    /**
     * Execute the job.
     *
     * @param BoardGameGeekPlaySyncService $syncService
     * @return void
     */
    public function handle(BoardGameGeekPlaySyncService $syncService): void
    {
        $user = User::find($this->userId);
        if ($user === null) {
            Log::warning('ImportBoardGamePlayFromBoardGameGeekJob: user not found', [
                'user_id' => $this->userId,
            ]);
            return;
        }

        $bggGameId = (string) ($this->playPayload['bgg_game_id'] ?? '');
        if ($bggGameId === '') {
            Log::error('ImportBoardGamePlayFromBoardGameGeekJob: missing bgg_game_id in payload');
            return;
        }

        $boardGame = BoardGame::where('bgg_id', $bggGameId)->first();
        if ($boardGame === null) {
            Log::warning('ImportBoardGamePlayFromBoardGameGeekJob: board game not yet synced, will retry', [
                'bgg_game_id' => $bggGameId,
                'bgg_play_id' => $this->playPayload['bgg_play_id'] ?? null,
            ]);
            throw new \RuntimeException("Board game with BGG ID {$bggGameId} not found; sync may not have completed yet");
        }

        if ($boardGame->is_expansion) {
            Log::info('ImportBoardGamePlayFromBoardGameGeekJob: skipping play for expansion', [
                'bgg_game_id' => $bggGameId,
                'bgg_play_id' => $this->playPayload['bgg_play_id'] ?? null,
            ]);
            return;
        }

        $syncService->createPlayFromPayload($this->playPayload, $user, $boardGame->id);

        Log::info('ImportBoardGamePlayFromBoardGameGeekJob: play imported successfully', [
            'bgg_play_id' => $this->playPayload['bgg_play_id'] ?? null,
            'bgg_game_id' => $bggGameId,
        ]);
    }
}
