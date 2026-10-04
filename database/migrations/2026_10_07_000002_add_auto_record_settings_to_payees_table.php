<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('payees', function (Blueprint $table) {
            // follow_global, always, never
            $table->string('auto_record_policy', 16)->default('follow_global')->after('category_suggestion_dismissed');
            $table->boolean('itemization_expected')->default(false)->after('auto_record_policy');
        });
    }

    public function down(): void
    {
        Schema::table('payees', function (Blueprint $table) {
            $table->dropColumn(['auto_record_policy', 'itemization_expected']);
        });
    }
};
