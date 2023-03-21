<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCurrenciesTable extends Migration
{
    /**
     * Run the migrations.
     *
     */
    public function up()
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191)->unique();
            $table->string('iso_code', 3)->unique();
            $table->tinyInteger('num_digits')->default('0');
            $table->string('suffix', 5)->nullable();
            $table->boolean('base')->nullable()->unique();
            $table->boolean('auto_update')->default('0');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     */
    public function down()
    {
        Schema::dropIfExists('currencies');
    }
}
