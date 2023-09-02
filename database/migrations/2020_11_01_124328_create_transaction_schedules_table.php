<?php

use App\Models\Transaction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     */
    public function up(): void
    {
        Schema::create('transaction_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Transaction::class)->constrained()->cascadeOnDelete();
            $table->boolean('automatic_recording')->default(false);
            $table->date('start_date');
            $table->date('next_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('frequency');
            $table->integer('interval')->default(1);
            $table->integer('count')->nullable();
            $table->float('inflation')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_schedules');
    }
};
