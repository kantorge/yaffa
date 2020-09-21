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
            $table->foreignId('investment_groups_id');
            $table->foreignId('currencies_id');

            $table->timestamps();

            $table->foreign('investment_groups_id')->references('id')->on('investment_groups');
            $table->foreign('currencies_id')->references('id')->on('currencies');

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
