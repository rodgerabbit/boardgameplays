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
        Schema::create('bgg_plays_syncs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('synced_at');
            $table->string('status', 50)->default('pending')->comment('pending, success, failed');
            $table->text('error_message')->nullable();
            $table->unsignedInteger('plays_count')->nullable();
            $table->boolean('requested_manually')->default(false);
            $table->timestamps();
        });

        Schema::table('bgg_plays_syncs', function (Blueprint $table) {
            $table->index(['user_id', 'synced_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bgg_plays_syncs');
    }
};
