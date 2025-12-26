<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * BoardGame model representing a board game entity.
 *
 * This model represents a board game with its metadata such as name, description,
 * player count, playing time, publisher, designer, optional BoardGameGeek ID,
 * BoardGameGeek rating (0-10 scale), complexity rating (0-5 scale), thumbnail URL,
 * and expansion status.
 */
class BoardGame extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'description',
        'min_players',
        'max_players',
        'playing_time_minutes',
        'year_published',
        'publisher',
        'designer',
        'image_url',
        'thumbnail_url',
        'bgg_id',
        'bgg_rating',
        'complexity_rating',
        'is_expansion',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'min_players' => 'integer',
            'max_players' => 'integer',
            'playing_time_minutes' => 'integer',
            'year_published' => 'integer',
            'bgg_rating' => 'decimal:3',
            'complexity_rating' => 'decimal:3',
            'is_expansion' => 'boolean',
        ];
    }
}
