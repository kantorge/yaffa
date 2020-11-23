<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInvestmentPriceProvidersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('investment_price_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191)->unique();
        });

        DB::table('investment_price_providers')->insert(
            array(
                'name' => 'Alpha Vantage',
            )
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('investment_price_providers');
    }
}
