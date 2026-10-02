<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('ai_documents', function (Blueprint $table) {
            $table->timestamp('captured_at')->nullable()->after('custom_prompt');
            $table->text('note')->nullable()->after('captured_at');
        });
    }

    public function down(): void
    {
        Schema::table('ai_documents', function (Blueprint $table) {
            $table->dropColumn(['captured_at', 'note']);
        });
    }
};
