<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * NULL means that finalized AI documents are kept forever (the default).
     */
    public function up(): void
    {
        Schema::table('ai_user_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('document_retention_days')
                ->nullable()
                ->after('category_matching_mode');
        });
    }

    public function down(): void
    {
        Schema::table('ai_user_settings', function (Blueprint $table) {
            $table->dropColumn('document_retention_days');
        });
    }
};
