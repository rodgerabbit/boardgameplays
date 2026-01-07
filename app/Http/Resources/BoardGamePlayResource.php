<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for BoardGamePlay model.
 *
 * Transforms the BoardGamePlay model into a consistent JSON structure for API responses.
 */
class BoardGamePlayResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'board_game_id' => $this->board_game_id,
            'group_id' => $this->group_id,
            'created_by_user_id' => $this->created_by_user_id,
            'played_at' => $this->played_at?->format('Y-m-d'),
            'location' => $this->location,
            'comment' => $this->comment,
            'game_length_minutes' => $this->game_length_minutes,
            'source' => $this->source,
            'bgg_play_id' => $this->bgg_play_id,
            'bgg_synced_at' => $this->bgg_synced_at?->toIso8601String(),
            'bgg_sync_status' => $this->bgg_sync_status,
            'bgg_sync_error_message' => $this->bgg_sync_error_message,
            'bgg_synced_to_at' => $this->bgg_synced_to_at?->toIso8601String(),
            'bgg_sync_to_status' => $this->bgg_sync_to_status,
            'bgg_sync_to_error_message' => $this->bgg_sync_to_error_message,
            'sync_to_bgg' => $this->sync_to_bgg,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        // Include board game if loaded
        if ($this->relationLoaded('boardGame')) {
            $data['board_game'] = new BoardGameResource($this->boardGame);
        }

        // Include group if loaded
        if ($this->relationLoaded('group')) {
            $data['group'] = new GroupResource($this->group);
        }

        // Include creator if loaded
        if ($this->relationLoaded('creator')) {
            $data['creator'] = new UserResource($this->creator);
        }

        // Include players if loaded
        if ($this->relationLoaded('players')) {
            $data['players'] = BoardGamePlayPlayerResource::collection($this->players);
        }

        // Include expansions if loaded
        if ($this->relationLoaded('expansions')) {
            $data['expansions'] = BoardGameResource::collection($this->expansions);
        }

        return $data;
    }
}

