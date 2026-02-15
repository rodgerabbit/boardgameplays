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
        Schema::create('bgg_collection_syncs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('synced_at');
            $table->string('status', 50)->default('pending')->comment('pending, success, failed');
            $table->text('error_message')->nullable();
            $table->unsignedInteger('items_count')->nullable();
            $table->timestamps();
        });

        Schema::table('bgg_collection_syncs', function (Blueprint $table) {
            $table->index(['user_id', 'synced_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bgg_collection_syncs');
    }
};
