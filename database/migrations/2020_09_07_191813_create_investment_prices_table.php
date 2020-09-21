<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInvestmentPricesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('investment_prices', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->foreignId('investments_id');
            $table->decimal('price', 10, 4);
            $table->timestamps();

            $table->foreign('investments_id')->references('id')->on('investments');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('investment_prices');
    }
}
