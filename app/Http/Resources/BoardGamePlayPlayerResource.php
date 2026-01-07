<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for BoardGamePlayPlayer model.
 *
 * Transforms the BoardGamePlayPlayer model into a consistent JSON structure for API responses.
 */
class BoardGamePlayPlayerResource extends JsonResource
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
            'board_game_play_id' => $this->board_game_play_id,
            'user_id' => $this->user_id,
            'board_game_geek_username' => $this->board_game_geek_username,
            'guest_name' => $this->guest_name,
            'score' => $this->score !== null ? (float) $this->score : null,
            'is_winner' => $this->is_winner,
            'is_new_player' => $this->is_new_player,
            'position' => $this->position,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        // Include player identifier (display name)
        $data['player_identifier'] = $this->getPlayerIdentifier();

        // Include user if loaded
        if ($this->relationLoaded('user')) {
            $data['user'] = new UserResource($this->user);
        }

        return $data;
    }
}

