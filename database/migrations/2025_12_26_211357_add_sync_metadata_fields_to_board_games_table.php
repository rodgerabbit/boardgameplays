<?php

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
        Schema::table('board_games', function (Blueprint $table) {
            $table->timestamp('bgg_synced_at')->nullable()->after('bgg_id')->comment('Timestamp when the board game was last synced from BoardGameGeek');
            $table->string('bgg_sync_status')->nullable()->after('bgg_synced_at')->comment('Status of the last sync attempt: success, failed, pending');
            $table->text('bgg_sync_error_message')->nullable()->after('bgg_sync_status')->comment('Error message if the last sync attempt failed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('board_games', function (Blueprint $table) {
            $table->dropColumn(['bgg_synced_at', 'bgg_sync_status', 'bgg_sync_error_message']);
        });
    }
};
