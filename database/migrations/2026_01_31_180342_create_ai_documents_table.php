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
        Schema::create('ai_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->string('status', 50)->default('draft');
            $table->string('source_type', 50);
            $table->json('processed_transaction_data')->nullable();
            $table->string('google_drive_file_id', 255)->nullable()->unique();
            $table->foreignId('received_mail_id')
                ->nullable()
                ->constrained('received_mails')
                ->cascadeOnDelete();
            $table->text('custom_prompt')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('status');
            $table->index('source_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_documents');
    }
};
