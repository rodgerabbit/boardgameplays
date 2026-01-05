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
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('default_group_id')
                ->nullable()
                ->after('email_verified_at')
                ->constrained('groups')
                ->onDelete('set null');
            
            $table->enum('theme_preference', ['light', 'dark', 'system'])
                ->default('system')
                ->after('default_group_id');
            
            $table->unsignedTinyInteger('play_notification_delay_hours')
                ->default(0)
                ->after('theme_preference');
            
            $table->index('default_group_id');
            $table->index('theme_preference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['default_group_id']);
            $table->dropIndex(['default_group_id']);
            $table->dropIndex(['theme_preference']);
            $table->dropColumn([
                'default_group_id',
                'theme_preference',
                'play_notification_delay_hours',
            ]);
        });
    }
};
