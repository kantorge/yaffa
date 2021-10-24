<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInvestmentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('investments', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191)->unique();
            $table->string('symbol', 191)->unique();
            $table->string('comment', 191)->nullable();
            $table->boolean('active')->default('1');
            $table->boolean('auto_update')->default('0');
            $table->foreignId('investment_group_id');
            $table->foreignId('currency_id');

            $table->timestamps();

            $table->foreign('investment_group_id')->references('id')->on('investment_groups');
            $table->foreign('currency_id')->references('id')->on('currencies');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('investments');
    }
}
