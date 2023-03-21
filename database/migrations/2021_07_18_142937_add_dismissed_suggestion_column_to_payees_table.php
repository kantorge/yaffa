<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDismissedSuggestionColumnToPayeesTable extends Migration
{
    /**
     * Run the migrations.
     *
     */
    public function up()
    {
        Schema::table('payees', function (Blueprint $table) {
            $table->timestamp('category_suggestion_dismissed')->nullable()->after('category_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     */
    public function down()
    {
        Schema::table('payees', function (Blueprint $table) {
            $table->dropColumn('category_suggestion_dismissed');
        });
    }
}
