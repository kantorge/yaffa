<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('import_row_hashes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('row_hash', 64);
            $table->timestamps();

            $table->unique(['user_id', 'row_hash']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('import_row_hashes');
    }
};
