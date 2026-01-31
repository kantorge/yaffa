<?php

use App\Models\Transaction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create AiDocument records for existing processed received mails to preserve data integrity
        $this->migrateReceivedMailsToAiDocuments();

        Schema::table('received_mails', function (Blueprint $table) {
            // Drop the old columns
            $table->dropColumn('transaction_data');
            $table->dropColumn('processed');
            $table->dropColumn('handled');
            $table->dropForeign('received_mails_transaction_id_foreign');
            $table->dropColumn('transaction_id');
        });
    }

    private function migrateReceivedMailsToAiDocuments(): void
    {
        $receivedMails = \DB::table('received_mails')
            ->where('processed', true)
            ->get();

        foreach ($receivedMails as $mail) {
            // Create AiDocument record for this received mail
            $aiDocumentId = \DB::table('ai_documents')->insertGetId([
                'user_id' => $mail->user_id,
                'status' => $mail->transaction_id ? 'finalized' : 'ready_for_review',
                'source_type' => 'received_email',
                'processed_transaction_data' => $mail->transaction_data,
                'google_drive_file_id' => null,
                'received_mail_id' => $mail->id,
                'custom_prompt' => null,
                'processed_at' => now(),
                'created_at' => $mail->created_at ?? now(),
                'updated_at' => $mail->updated_at ?? now(),
            ]);

            // Update linked transaction to reference the new AiDocument record
            if ($mail->transaction_id) {
                \DB::table('transactions')
                    ->where('id', $mail->transaction_id)
                    ->update(['ai_document_id' => $aiDocumentId]);
            }
        }
    }

    /**
     * Reverse the migrations.
     * NO DATA WILL BE RESTORED DURING ROLLBACK
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
