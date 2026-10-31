<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Supports the mobile app's incremental polling (`updated_since`, `since`).
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('ai_documents', function (Blueprint $table) {
            $table->index(['user_id', 'updated_at']);
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['notifiable_type', 'notifiable_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('ai_documents', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'updated_at']);
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex(['notifiable_type', 'notifiable_id', 'created_at']);
        });
    }
};
