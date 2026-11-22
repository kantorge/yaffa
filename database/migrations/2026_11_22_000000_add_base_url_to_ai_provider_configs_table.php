<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('ai_provider_configs', function (Blueprint $table) {
            // NULL = provider's official endpoint (all pre-existing rows)
            $table->string('base_url', 2048)->nullable()->after('api_key');
        });
    }

    public function down(): void
    {
        Schema::table('ai_provider_configs', function (Blueprint $table) {
            $table->dropColumn('base_url');
        });
    }
};
