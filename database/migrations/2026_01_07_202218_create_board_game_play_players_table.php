<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('board_game_play_players', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('board_game_play_id')->constrained('board_game_plays')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->string('board_game_geek_username')->nullable()->comment('BGG username (mutually exclusive with other identifiers)');
            $table->string('guest_name')->nullable()->comment('Guest name (mutually exclusive with other identifiers)');
            $table->decimal('score', 10, 2)->nullable();
            $table->boolean('is_winner')->default(false);
            $table->boolean('is_new_player')->default(false)->comment('Whether this is player\'s first play of this boardgame');
            $table->integer('position')->nullable()->comment('Player position/rank in the game');
            $table->timestamps();

            $table->index('board_game_play_id');
            $table->index('user_id');
            $table->index('board_game_geek_username');
        });

        // Add check constraint to ensure exactly one identifier is set
        // Note: PostgreSQL supports check constraints, MySQL/MariaDB may need application-level validation
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('
                ALTER TABLE board_game_play_players
                ADD CONSTRAINT check_exactly_one_player_identifier
                CHECK (
                    (user_id IS NOT NULL AND board_game_geek_username IS NULL AND guest_name IS NULL) OR
                    (user_id IS NULL AND board_game_geek_username IS NOT NULL AND guest_name IS NULL) OR
                    (user_id IS NULL AND board_game_geek_username IS NULL AND guest_name IS NOT NULL)
                )
            ');
        }

        // Add unique constraint to prevent duplicate players in same play
        // This will be based on the identifier type (user_id, board_game_geek_username, or guest_name)
        // We'll handle this at the application level for cross-database compatibility
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE board_game_play_players DROP CONSTRAINT IF EXISTS check_exactly_one_player_identifier');
        }
        Schema::dropIfExists('board_game_play_players');
    }
};
