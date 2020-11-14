<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCurrencyRatesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('currency_rates', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->foreignId('from_id');
            $table->foreignId('to_id');

            $table->timestamps();

            $table->date('date');
            $table->decimal('rate', 8, 4);

            $table->foreign('from_id')->references('id')->on('currencies');
            $table->foreign('to_id')->references('id')->on('currencies');

            $table->unique(['date', 'from_id', 'to_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('currency_rates');
    }
}
