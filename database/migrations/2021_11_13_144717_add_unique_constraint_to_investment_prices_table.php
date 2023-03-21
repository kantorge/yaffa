<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUniqueConstraintToInvestmentPricesTable extends Migration
{
    /**
     * Run the migrations.
     *
     */
    public function up()
    {
        Schema::table('investment_prices', function (Blueprint $table) {
            $table->unique(['date', 'investment_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     */
    public function down()
    {
        Schema::table('investment_prices', function (Blueprint $table) {
            $table->dropUnique('investment_prices_date_investment_id_unique');
        });
    }
}
