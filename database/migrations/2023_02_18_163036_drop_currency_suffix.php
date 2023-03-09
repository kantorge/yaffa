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
    public function up(): void
    {
        Schema::table('currencies', function (Blueprint $table) {
            $table->dropColumn('suffix');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('currencies', function (Blueprint $table) {
            $table->addColumn('string', 'suffix', ['length' => 5])->after('num_digits')->nullable();
        });
    }
};
