<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('ai_documents', function (Blueprint $table) {
            $table->char('content_hash', 64)->nullable()->after('custom_prompt');
            $table->string('document_kind', 32)->nullable()->after('content_hash');
            $table->timestamp('status_changed_at')->nullable()->after('processed_at');

            $table->index(['user_id', 'content_hash']);
        });

        DB::table('ai_documents')->update(['status_changed_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('ai_documents', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'content_hash']);
            $table->dropColumn(['content_hash', 'document_kind', 'status_changed_at']);
        });
    }
};
