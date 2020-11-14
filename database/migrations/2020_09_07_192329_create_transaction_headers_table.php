<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransactionHeadersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->date('date')->nullable();
            $table->foreignId('transaction_type_id');
            $table->boolean('reconciled')->default('0');
            $table->boolean('schedule')->default('0');
            $table->boolean('budget')->default('0');
            $table->string('comment', 191)->nullable();

            $table->string('config_type')->nullable();
            $table->unsignedInteger('config_id')->nullable();

            $table->timestamps();
            //$table->softDeletes();

            $table->foreign('transaction_type_id')->references('id')->on('transaction_types');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transactions');
    }
}
