<?php

use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('received_mails', function (Blueprint $table) {
            $table->id();
            $table->string('message_id');
            $table->string('subject');
            $table->longText('html');
            $table->longText('text');

            // Json of the extracted transaction data
            $table->json('transaction_data')->nullable();

            // User who sent the email, reference to users table
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();

            // Indicates if the email has been processed by AI
            $table->boolean('processed')->default(false);

            // Indicates if the processed email was handled by the user
            $table->boolean('handled')->default(false);

            // Reference to the transaction that was created for this email
            $table->foreignId('transaction_id')
                ->nullable()
                ->constrained('transactions')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->timestamps();
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('received_mails');
    }
};
