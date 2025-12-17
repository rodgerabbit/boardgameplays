<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * BoardGameResource for transforming BoardGame model to API response.
 *
 * This resource formats board game data for API responses, ensuring consistent
 * structure and including all relevant board game information.
 */
class BoardGameResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'min_players' => $this->min_players,
            'max_players' => $this->max_players,
            'playing_time_minutes' => $this->playing_time_minutes,
            'year_published' => $this->year_published,
            'publisher' => $this->publisher,
            'designer' => $this->designer,
            'image_url' => $this->image_url,
            'bgg_id' => $this->bgg_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
