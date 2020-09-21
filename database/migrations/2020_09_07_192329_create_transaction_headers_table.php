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
        Schema::create('transaction_headers', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->foreignId('transaction_types_id');
            $table->boolean('reconciled')->default('0');
            $table->boolean('is_schedule')->default('0');
            $table->boolean('is_budget')->default('0');
            $table->string('comment', 191)->nullable();

            $table->string('config_type')->nullable();
            $table->unsignedInteger('config_id')->nullable();

            $table->timestamps();

            $table->foreign('transaction_types_id')->references('id')->on('transaction_types');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transaction_headers');
    }
}
