<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Data Transfer Object for BoardGameGeek game data.
 *
 * This DTO represents a board game retrieved from the BoardGameGeek XML API.
 * It contains all the relevant information about a board game including
 * name, description, player counts, ratings, and other metadata.
 */
class BoardGameGeekGameDto extends BaseDTO
{
    /**
     * Create a new BoardGameGeekGameDto instance.
     *
     * @param string $bggId The BoardGameGeek unique identifier
     * @param string $name The name of the board game
     * @param string|null $description The description of the board game
     * @param int|null $minPlayers The minimum number of players
     * @param int|null $maxPlayers The maximum number of players
     * @param int|null $playingTimeMinutes The playing time in minutes
     * @param int|null $yearPublished The year the game was published
     * @param string|null $publisher The publisher of the game
     * @param string|null $designer The designer of the game
     * @param string|null $imageUrl The URL to the full-size image
     * @param string|null $thumbnailUrl The URL to the thumbnail image
     * @param float|null $bggRating The BoardGameGeek rating (0-10 scale)
     * @param float|null $complexityRating The complexity rating (0-5 scale)
     * @param bool $isExpansion Whether this is an expansion
     */
    public function __construct(
        public readonly string $bggId,
        public readonly string $name,
        public readonly ?string $description = null,
        public readonly ?int $minPlayers = null,
        public readonly ?int $maxPlayers = null,
        public readonly ?int $playingTimeMinutes = null,
        public readonly ?int $yearPublished = null,
        public readonly ?string $publisher = null,
        public readonly ?string $designer = null,
        public readonly ?string $imageUrl = null,
        public readonly ?string $thumbnailUrl = null,
        public readonly ?float $bggRating = null,
        public readonly ?float $complexityRating = null,
        public readonly bool $isExpansion = false,
    ) {
    }

    /**
     * Convert the DTO to an array suitable for database insertion.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'bgg_id' => $this->bggId,
            'name' => $this->name,
            'description' => $this->description,
            'min_players' => $this->minPlayers,
            'max_players' => $this->maxPlayers,
            'playing_time_minutes' => $this->playingTimeMinutes,
            'year_published' => $this->yearPublished,
            'publisher' => $this->publisher,
            'designer' => $this->designer,
            'image_url' => $this->imageUrl,
            'thumbnail_url' => $this->thumbnailUrl,
            'bgg_rating' => $this->bggRating,
            'complexity_rating' => $this->complexityRating,
            'is_expansion' => $this->isExpansion,
        ];
    }
}


