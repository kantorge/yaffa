<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('received_mails', function (Blueprint $table) {
            // Drop the old columns
            if (Schema::hasColumn('received_mails', 'transaction_data')) {
                $table->dropColumn('transaction_data');
            }
            if (Schema::hasColumn('received_mails', 'processed')) {
                $table->dropColumn('processed');
            }
            if (Schema::hasColumn('received_mails', 'handled')) {
                $table->dropColumn('handled');
            }
            if (Schema::hasColumn('received_mails', 'transaction_id')) {
                $table->dropForeignIdFor('transactions');
                $table->dropColumn('transaction_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('received_mails', function (Blueprint $table) {
            $table->json('transaction_data')->nullable();
            $table->boolean('processed')->default(false);
            $table->boolean('handled')->default(false);
            $table->foreignId('transaction_id')
                ->nullable()
                ->constrained('transactions')
                ->cascadeOnDelete();
        });
    }
};
