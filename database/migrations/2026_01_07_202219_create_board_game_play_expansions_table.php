<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('board_game_play_expansions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('board_game_play_id')->constrained('board_game_plays')->onDelete('cascade');
            $table->foreignId('board_game_id')->constrained('board_games')->onDelete('cascade')->comment('Must be an expansion');
            $table->timestamps();

            $table->unique(['board_game_play_id', 'board_game_id']);
            $table->index('board_game_play_id');
            $table->index('board_game_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('board_game_play_expansions');
    }
};
