<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Only one user can have a given BoardGameGeek username.
     * Partial unique index allows multiple NULLs.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX users_board_game_geek_username_unique ON users (board_game_geek_username) WHERE board_game_geek_username IS NOT NULL');
        } else {
            // MySQL: unique index on nullable column allows multiple NULLs
            DB::statement('CREATE UNIQUE INDEX users_board_game_geek_username_unique ON users (board_game_geek_username)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS users_board_game_geek_username_unique');
        } else {
            DB::statement('DROP INDEX users_board_game_geek_username_unique ON users');
        }
    }
};
