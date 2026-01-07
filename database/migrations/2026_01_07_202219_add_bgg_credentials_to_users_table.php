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
        Schema::table('users', function (Blueprint $table): void {
            // board_game_geek_username already exists with an index, so we only add the new fields
            $table->text('board_game_geek_password_encrypted')->nullable()->after('board_game_geek_username')->comment('Encrypted BGG password for user\'s own credentials');
            $table->boolean('sync_plays_to_board_game_geek')->default(false)->after('board_game_geek_password_encrypted')->comment('Whether user wants to sync plays to BGG');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Note: We don't drop the board_game_geek_username index here because it was created in a previous migration
            $table->dropColumn(['board_game_geek_password_encrypted', 'sync_plays_to_board_game_geek']);
        });
    }
};
