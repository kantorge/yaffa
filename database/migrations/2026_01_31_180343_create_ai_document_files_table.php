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
        Schema::create('ai_document_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_document_id')
                ->constrained('ai_documents')
                ->cascadeOnDelete();
            $table->string('file_path', 500);
            $table->string('file_name', 255);
            $table->string('file_type', 10);
            $table->timestamp('created_at');

            $table->index('ai_document_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_document_files');
    }
};
