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
        Schema::create('board_game_plays', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('board_game_id')->constrained('board_games')->onDelete('cascade');
            $table->foreignId('group_id')->nullable()->constrained('groups')->onDelete('set null');
            $table->foreignId('created_by_user_id')->constrained('users')->onDelete('cascade');
            $table->date('played_at');
            $table->string('location');
            $table->text('comment')->nullable();
            $table->integer('game_length_minutes')->nullable();
            $table->enum('source', ['website', 'boardgamegeek'])->default('website');
            $table->string('bgg_play_id')->nullable()->unique()->comment('BoardGameGeek play ID if synced from/to BGG');
            $table->timestamp('bgg_synced_at')->nullable()->comment('When synced from BGG');
            $table->string('bgg_sync_status')->nullable()->comment('Sync status for incoming syncs');
            $table->text('bgg_sync_error_message')->nullable()->comment('Sync error message for incoming syncs');
            $table->timestamp('bgg_synced_to_at')->nullable()->comment('When synced to BGG');
            $table->string('bgg_sync_to_status')->nullable()->comment('Sync status for outgoing syncs (pending, synced, failed)');
            $table->text('bgg_sync_to_error_message')->nullable()->comment('Sync error message for outgoing syncs');
            $table->boolean('sync_to_bgg')->default(false)->comment('Whether to sync this play to BGG');
            $table->timestamps();

            $table->index('board_game_id');
            $table->index('group_id');
            $table->index('created_by_user_id');
            $table->index('played_at');
            $table->index('source');
            $table->index('bgg_play_id');
            $table->index('sync_to_bgg');
            $table->index('bgg_sync_to_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('board_game_plays');
    }
};
