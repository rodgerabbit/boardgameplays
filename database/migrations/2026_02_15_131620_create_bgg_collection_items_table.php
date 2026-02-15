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
        Schema::create('bgg_collection_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('bgg_id', 20)->comment('Base/original game BGG ID for linking to board_games');
            $table->string('bgg_object_id', 20)->comment('BGG object ID from collection (may be version or base)');
            $table->string('bgg_version_id', 20)->nullable()->comment('BGG version ID when object is a version');
            $table->foreignId('board_game_id')->nullable()->constrained('board_games')->nullOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('year_published')->nullable();
            $table->string('thumbnail_url', 500)->nullable();
            $table->decimal('user_rating', 4, 2)->nullable();
            $table->boolean('owned')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });

        Schema::table('bgg_collection_items', function (Blueprint $table) {
            $table->unique(['user_id', 'bgg_object_id']);
            $table->index(['user_id', 'bgg_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bgg_collection_items');
    }
};
