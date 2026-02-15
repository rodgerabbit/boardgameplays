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
        Schema::table('bgg_collection_items', function (Blueprint $table) {
            $table->unsignedInteger('numplays')->default(0)->after('owned')->comment('Number of plays recorded on BGG');
            $table->string('bgg_collid', 20)->nullable()->after('bgg_version_id')->comment('BGG collection entry ID');
            $table->string('image_url', 500)->nullable()->after('thumbnail_url')->comment('Full-size image URL from BGG');
            $table->timestamp('bgg_last_modified')->nullable()->after('last_synced_at')->comment('When user last modified this item on BGG');
            $table->boolean('prev_owned')->default(false)->after('owned');
            $table->boolean('for_trade')->default(false)->after('prev_owned');
            $table->boolean('want')->default(false)->after('for_trade');
            $table->boolean('want_to_play')->default(false)->after('want');
            $table->boolean('want_to_buy')->default(false)->after('want_to_play');
            $table->boolean('wishlist')->default(false)->after('want_to_buy');
            $table->boolean('preordered')->default(false)->after('wishlist');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bgg_collection_items', function (Blueprint $table) {
            $table->dropColumn([
                'numplays', 'bgg_collid', 'image_url', 'bgg_last_modified',
                'prev_owned', 'for_trade', 'want', 'want_to_play', 'want_to_buy', 'wishlist', 'preordered',
            ]);
        });
    }
};
