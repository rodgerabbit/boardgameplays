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
            $table->boolean('use_generic_user_for_bgg_plays')
                ->default(true)
                ->after('sync_plays_to_board_game_geek')
                ->comment('When true, use .env generic credentials to log plays to BGG; when false, use user BGG username and stored password');
            $table->timestamp('bgg_manual_sync_requested_at')
                ->nullable()
                ->after('use_generic_user_for_bgg_plays')
                ->comment('When the user last requested a manual BGG sync (for 24h cooldown)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['use_generic_user_for_bgg_plays', 'bgg_manual_sync_requested_at']);
        });
    }
};
