<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAccountEntitiesTable extends Migration
{
    /**
     * Run the migrations.
     *
     */
    public function up()
    {
        Schema::create('account_entities', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191);
            $table->boolean('active')->default('1');
            $table->string('config_type');
            $table->unsignedInteger('config_id');
            $table->timestamps();

            $table->unique(['config_type', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     *
     */
    public function down()
    {
        Schema::dropIfExists('account_entities');
    }
}
