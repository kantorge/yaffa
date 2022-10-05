<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('account_entity_category_preference', function (Blueprint $table) {
            $table->bigInteger('account_entity_id')->unsigned();
            $table->foreign('account_entity_id')
                ->references('id')
                ->on('account_entities')
                ->onDelete('cascade');

            $table->bigInteger('category_id')->unsigned();
            $table->foreign('category_id')
                ->references('id')
                ->on('categories')
                ->onDelete('cascade');

            $table->boolean('preferred')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('account_entity_category_preference');
    }
};
