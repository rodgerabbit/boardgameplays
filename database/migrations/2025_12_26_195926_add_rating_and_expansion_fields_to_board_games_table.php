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
            $table->decimal('bgg_rating', 5, 3)->nullable()->after('bgg_id')->comment('BoardGameGeek rating on a 0-10 scale with 3 decimal places');
            $table->decimal('complexity_rating', 5, 3)->nullable()->after('bgg_rating')->comment('Complexity rating on a 0-5 scale with 3 decimal places');
            $table->string('thumbnail_url')->nullable()->after('image_url')->comment('URL to the thumbnail image of the board game');
            $table->boolean('is_expansion')->default(false)->after('complexity_rating')->comment('Indicates if this entry is an expansion rather than a base game');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('board_games', function (Blueprint $table) {
            $table->dropColumn(['bgg_rating', 'complexity_rating', 'thumbnail_url', 'is_expansion']);
        });
    }
};
