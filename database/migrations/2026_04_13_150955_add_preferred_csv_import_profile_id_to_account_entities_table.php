<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('account_entities', function (Blueprint $table) {
            $table->foreignId('preferred_csv_import_profile_id')
                ->nullable()
                ->after('config_id')
                ->constrained('csv_import_profiles')
                ->nullOnDelete();

            $table->index('preferred_csv_import_profile_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('account_entities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('preferred_csv_import_profile_id');
        });
    }
};
