<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     */
    public function up(): void
    {
        // Create the new column in the account_entities table
        Schema::table('account_entities', function (Blueprint $table) {
            $table->text('alias')->nullable();
        });

        // Populate the new column with the values from the payees config table
        DB::table('account_entities')
            ->join('payees', 'account_entities.config_id', '=', 'payees.id')
            ->where('account_entities.config_type', 'payee')
            ->update(['account_entities.alias' => DB::raw('payees.import_alias')]);

        // Drop the import_alias column from the payees config table
        Schema::table('payees', function (Blueprint $table) {
            $table->dropColumn('import_alias');
        });
    }

    /**
     * Reverse the migrations.
     *
     */
    public function down()
    {
        // Create the import_alias column in the payees config table
        Schema::table('payees', function (Blueprint $table) {
            $table->text('import_alias')->nullable();
        });

        // Populate the new column with the values from the account_entities table
        DB::table('payees')
            ->join('account_entities', 'payees.id', '=', 'account_entities.config_id')
            ->where('account_entities.config_type', 'payee')
            ->update(['payees.import_alias' => DB::raw('account_entities.alias')]);

        // Drop the alias column from the account_entities table
        Schema::table('account_entities', function (Blueprint $table) {
            $table->dropColumn('alias');
        });
    }
};
