<?php

use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     */
    public function up(): void
    {
        Schema::table('received_mails', function ($table) {
            $table->dropForeign('received_mails_transaction_id_foreign');

            $table->foreign('transaction_id')
                ->references('id')
                ->on('transactions')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     */
    public function down(): void
    {
        Schema::table('received_mails', function ($table) {
            $table->dropForeign('received_mails_transaction_id_foreign');

            $table->foreign('transaction_id')
                ->references('id')
                ->on('transactions');
        });
    }
};
