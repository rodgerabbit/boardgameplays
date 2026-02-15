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
        Schema::create('bgg_collection_item_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bgg_collection_sync_id')->nullable()->constrained('bgg_collection_syncs')->nullOnDelete();
            $table->string('change_type', 50)->comment('added, removed, rating_changed, etc.');
            $table->string('bgg_id', 20)->comment('Base game BGG ID');
            $table->string('bgg_object_id', 20)->nullable();
            $table->json('old_value')->nullable();
            $table->json('new_value')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::table('bgg_collection_item_changes', function (Blueprint $table) {
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bgg_collection_item_changes');
    }
};
