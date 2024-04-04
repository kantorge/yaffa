<?php

use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('currencies', function ($table) {
            $table->dropColumn('num_digits');
        });
    }

    /**
     * Reverse the migrations.
     * It will not be able to restore previous data, but 0 will be used as default value.
     */
    public function down(): void
    {
        Schema::table('currencies', function ($table) {
            $table->unsignedTinyInteger('num_digits')
                ->after('iso_code')
                ->default(0);
        });
    }
};
