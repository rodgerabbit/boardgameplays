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
        Schema::table('groups', function (Blueprint $table): void {
            $table->foreignId('created_by_user_id')
                ->nullable()
                ->after('id')
                ->constrained('users')
                ->onDelete('set null');
            
            $table->index('created_by_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table): void {
            $table->dropForeign(['created_by_user_id']);
            $table->dropIndex(['created_by_user_id']);
            $table->dropColumn('created_by_user_id');
        });
    }
};
