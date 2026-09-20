<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Documents created from pasted text stored the boolean result of Storage::put() as file_path
 * ("1") instead of the real path, so their file could neither be read nor cleaned up. The files
 * themselves were written to the standard location, which this restores in the rows.
 */
return new class () extends Migration {
    public function up(): void
    {
        DB::table('ai_document_files')
            ->join('ai_documents', 'ai_documents.id', '=', 'ai_document_files.ai_document_id')
            ->where('ai_document_files.file_path', '1')
            ->where('ai_document_files.file_name', 'like', 'text\_input\_%')
            ->update([
                'ai_document_files.file_path' => DB::raw(
                    "CONCAT('ai_documents/', ai_documents.user_id, '/', ai_documents.id, '/', ai_document_files.file_name)"
                ),
            ]);
    }

    /**
     * The previous values were invalid, so there is nothing worth restoring.
     */
    public function down(): void
    {
    }
};
