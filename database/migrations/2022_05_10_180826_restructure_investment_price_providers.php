<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RestructureInvestmentPriceProviders extends Migration
{
    /**
     * Run the migrations.
     *
     */
    public function up()
    {
        // Create investment price provider (nullable string) column in investments table
        Schema::table('investments', function (Blueprint $table) {
            $table->string('investment_price_provider', 191)->nullable()->after('auto_update');
        });

        // Convert investment price provider ID into string. Currently only Alpha Vantage exists.
        DB::update('UPDATE investments SET investment_price_provider = "alpha_vantage" WHERE investment_price_provider_id = 1');

        // Drop investment price provider ID column
        Schema::table('investments', function (Blueprint $table) {
            $table->dropForeign('investments_investment_price_provider_id_foreign');
            $table->dropColumn('investment_price_provider_id');
        });

        // Drop investment price provider table
        Schema::dropIfExists('investment_price_providers');
    }

    /**
     * Reverse the migrations.
     *
     */
    public function down()
    {
        // Create investment price provider table
        Schema::create('investment_price_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191)->unique();
        });

        DB::table('investment_price_providers')->insert(
            [
                'name' => 'Alpha Vantage',
            ]
        );

        // Create investment price provider ID column in investments table referring to investment price provider table
        Schema::table('investments', function (Blueprint $table) {
            $table->foreignId('investment_price_provider_id')->nullable()->after('auto_update');
            $table->foreign('investment_price_provider_id')->references('id')->on('investment_price_providers');
        });

        // Convert investment price provider string into ID. Currently only Alpha Vantage exists.
        DB::update('UPDATE investments SET investment_price_provider_id = 1 WHERE investment_price_provider = "alpha_vantage"');

        // Drop investment price provider string column
        Schema::table('investments', function (Blueprint $table) {
            $table->dropColumn('investment_price_provider');
        });
    }
}
