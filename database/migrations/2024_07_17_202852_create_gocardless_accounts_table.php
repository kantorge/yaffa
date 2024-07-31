<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('gocardless_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('requisition_id');
            $table->foreign('requisition_id')->references('id')->on('gocardless_requisitions');
            $table->string('name');
            $table->string('iban')->nullable();
            $table->string('currency_code', 3);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gocardless_accounts');
    }
};
