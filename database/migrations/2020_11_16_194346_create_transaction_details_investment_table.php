<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransactionDetailsInvestmentTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transaction_details_investment', function (Blueprint $table) {
            $table->id();

            $table->foreignId('account_id');
            $table->foreignId('investment_id');

            $table->double('price', 10, 4);
            $table->double('quantity', 14, 4);
            $table->double('commission', 14, 4);
            $table->double('dividend', 12, 2);

            $table->foreign('account_id')->references('id')->on('account_entities');
            $table->foreign('investment_id')->references('id')->on('investments');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transaction_details_investment');
    }
}
