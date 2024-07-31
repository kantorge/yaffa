<?php

use App\Models\User;
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
        Schema::create('gocardless_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('gocardless_account_id');
            $table->foreign('gocardless_account_id')->references('id')->on('gocardless_accounts');
            $table->foreignIdFor(User::class);
            $table->string('transaction_id');
            $table->string('status');
            $table->date('booking_date')->nullable();
            $table->date('value_date');
            // This is intended to be a string to avoid floating point precision issues
            $table->string('transaction_amount');
            // Theoretically, this is defined by the account, but we save it for reference
            $table->string('currency_code', 3);
            $table->string('description')->nullable();
            $table->string('debtor_name')->nullable();
            $table->string('creditor_name')->nullable();
            // Due to the high variability of the data, we store the raw incoming data to JSON
            $table->json('raw_data');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gocardless_transactions');
    }
};
