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
            $table->boolean('is_incomplete')->default(false)->after('source')
                ->comment('BGG incomplete flag; incomplete plays are never chosen as leading in deduplication');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('board_game_plays', function (Blueprint $table): void {
            $table->dropColumn('is_incomplete');
        });
    }
};
