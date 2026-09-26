<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A transaction only references the AI document it was created from, it does not depend on it.
 * Deleting the document (manually, or by the retention cleanup) must keep the transaction and
 * just clear the link, instead of cascading the delete to the transaction.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign('transactions_ai_document_id_foreign');
            $table->foreign('ai_document_id')
                ->references('id')
                ->on('ai_documents')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign('transactions_ai_document_id_foreign');
            $table->foreign('ai_document_id')
                ->references('id')
                ->on('ai_documents')
                ->cascadeOnDelete();
        });
    }
};
