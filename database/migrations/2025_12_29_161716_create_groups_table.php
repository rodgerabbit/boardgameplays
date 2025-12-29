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
        Schema::create('groups', function (Blueprint $table): void {
            $table->id();
            $table->string('friendly_name');
            $table->text('description')->nullable();
            $table->string('group_location')->nullable();
            $table->string('website_link')->nullable();
            $table->string('discord_link')->nullable();
            $table->string('slack_link')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('deleted_at');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('groups');
    }
};
