<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * NULL falls back to the default, see AiUserSettingsResolver.
     */
    public function up(): void
    {
        Schema::table('ai_user_settings', function (Blueprint $table) {
            $table->decimal('auto_record_wilson_min', 4, 3)->nullable();
            $table->unsignedTinyInteger('auto_record_min_history')->nullable();
            $table->decimal('auto_record_amount_tolerance_percent', 5, 2)->nullable();
            $table->decimal('payee_similarity_min', 4, 3)->nullable();
            $table->decimal('payee_similarity_margin', 4, 3)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ai_user_settings', function (Blueprint $table) {
            $table->dropColumn([
                'auto_record_wilson_min',
                'auto_record_min_history',
                'auto_record_amount_tolerance_percent',
                'payee_similarity_min',
                'payee_similarity_margin',
            ]);
        });
    }
};
