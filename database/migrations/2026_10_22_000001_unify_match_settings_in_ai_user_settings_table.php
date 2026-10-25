<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Auto-recording no longer has matching rules of its own: the draft and the auto-record gates share the
     * asset matching settings. The payee similarity minimum is gone (the auto-accept threshold replaces it),
     * and the margin that a match has to lead by applies to every asset, not only to payees. Values already
     * set by a user are dropped (min) or carried over (margin).
     */
    public function up(): void
    {
        Schema::table('ai_user_settings', function (Blueprint $table) {
            $table->dropColumn('payee_similarity_min');
            $table->renameColumn('payee_similarity_margin', 'match_min_margin');
        });
    }

    public function down(): void
    {
        Schema::table('ai_user_settings', function (Blueprint $table) {
            $table->renameColumn('match_min_margin', 'payee_similarity_margin');
            $table->decimal('payee_similarity_min', 4, 3)->nullable();
        });
    }
};
