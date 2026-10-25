<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('ai_user_settings', function (Blueprint $table) {
            // Opt-in: nothing is recorded automatically unless the user turns this on
            $table->boolean('auto_record_enabled')->default(false);
            // NULL falls back to the default, see AiUserSettingsResolver
            $table->unsignedTinyInteger('itemization_timeout_days')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ai_user_settings', function (Blueprint $table) {
            $table->dropColumn(['auto_record_enabled', 'itemization_timeout_days']);
        });
    }
};
