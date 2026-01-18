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
        Schema::table('board_game_plays', function (Blueprint $table): void {
            $table->boolean('is_excluded')->default(false)->after('sync_to_bgg')->comment('Marks excluded duplicate plays');
            $table->foreignId('leading_play_id')->nullable()->after('is_excluded')->constrained('board_game_plays')->onDelete('set null')->comment('References the leading play in a duplicate group');
            $table->timestamp('excluded_at')->nullable()->after('leading_play_id')->comment('When the play was marked as excluded');
            $table->text('exclusion_reason')->nullable()->after('excluded_at')->comment('Reason for exclusion (for debugging/auditing)');

            // Add indexes for efficient queries
            $table->index('is_excluded', 'board_game_plays_is_excluded_index');
            $table->index('leading_play_id', 'board_game_plays_leading_play_id_index');
            $table->index(['board_game_id', 'played_at', 'group_id'], 'board_game_plays_deduplication_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('board_game_plays', function (Blueprint $table): void {
            $table->dropIndex('board_game_plays_deduplication_index');
            $table->dropIndex('board_game_plays_leading_play_id_index');
            $table->dropIndex('board_game_plays_is_excluded_index');
            $table->dropForeign(['leading_play_id']);
            $table->dropColumn(['is_excluded', 'leading_play_id', 'excluded_at', 'exclusion_reason']);
        });
    }
};
