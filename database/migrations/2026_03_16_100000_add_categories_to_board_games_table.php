<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds game categories synced from BoardGameGeek thing API (link type=boardgamecategory).
     * Stored as JSONB array of objects: [{"bgg_id": "1234", "name": "Economic"}, ...]
     */
    public function up(): void
    {
        Schema::table('board_games', function (Blueprint $table) {
            $table->jsonb('categories')->nullable()->after('is_expansion')->comment(
                'Game categories from BGG (boardgamecategory links): array of {bgg_id, name}'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('board_games', function (Blueprint $table) {
            $table->dropColumn('categories');
        });
    }
};
