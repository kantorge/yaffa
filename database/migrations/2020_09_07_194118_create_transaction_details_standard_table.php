<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransactionDetailsStandardTable extends Migration
{
    /**
     * Run the migrations.
     *
     */
    public function up()
    {
        Schema::create('transaction_details_standard', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_from_id')->nullable();
            $table->foreignId('account_to_id')->nullable();
            $table->decimal('amount_from', 12, 2);
            $table->decimal('amount_to', 12, 2);

            $table->foreign('account_from_id')->references('id')->on('account_entities');
            $table->foreign('account_to_id')->references('id')->on('account_entities');
        });
    }

    /**
     * Reverse the migrations.
     *
     */
    public function down()
    {
        Schema::dropIfExists('transaction_details_standard');
    }
}
