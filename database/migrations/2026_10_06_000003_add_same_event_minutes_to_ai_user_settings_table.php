<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * NULL falls back to the default (10 minutes), see AiUserSettingsResolver.
     */
    public function up(): void
    {
        Schema::table('ai_user_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('same_event_minutes')->nullable()->after('duplicate_similarity_threshold');
        });
    }

    public function down(): void
    {
        Schema::table('ai_user_settings', function (Blueprint $table) {
            $table->dropColumn('same_event_minutes');
        });
    }
};
