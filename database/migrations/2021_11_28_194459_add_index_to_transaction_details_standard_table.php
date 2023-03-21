<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndexToTransactionDetailsStandardTable extends Migration
{
    /**
     * Run the migrations.
     *
     */
    public function up()
    {
        Schema::table('transaction_details_standard', function (Blueprint $table) {
            $table->index(['account_from_id', 'account_to_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     */
    public function down()
    {
    }
}
