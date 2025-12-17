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
        Schema::create('board_games', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('min_players');
            $table->integer('max_players');
            $table->integer('playing_time_minutes')->nullable();
            $table->integer('year_published')->nullable();
            $table->string('publisher')->nullable();
            $table->string('designer')->nullable();
            $table->string('image_url')->nullable();
            $table->string('bgg_id')->nullable()->unique()->comment('BoardGameGeek ID');
            $table->timestamps();

            $table->index('name');
            $table->index('bgg_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('board_games');
    }
};
