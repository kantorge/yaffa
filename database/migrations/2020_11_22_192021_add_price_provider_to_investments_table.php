<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPriceProviderToInvestmentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('investments', function (Blueprint $table) {
            $table->foreignId('investment_price_provider_id')->nullable()->after('currency_id');
            $table->foreign('investment_price_provider_id')->references('id')->on('investment_price_providers');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('investments', function (Blueprint $table) {
            $table->dropForeign('investments_investment_price_provider_id_foreign');
            $table->dropColumn('investment_price_provider_id');
        });
    }
}
