<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('imported_investment_rows', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('account_id');
            $table->string('reference', 64)->nullable();
            $table->dateTime('date')->nullable();
            $table->string('transaction_type', 64)->nullable();
            $table->string('description', 255)->nullable();
            $table->decimal('amount', 20, 8)->nullable();
            $table->decimal('balance', 20, 8)->nullable();
            $table->json('raw_data')->nullable();
            $table->boolean('processed')->default(false);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('imported_investment_rows');
    }
};
