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
        Schema::table('transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('import_job_id')->nullable()->after('user_id');
            $table->index('import_job_id');

            // Add foreign key constraint (optional - allows easy cascading deletes)
            $table->foreign('import_job_id')
                ->references('id')
                ->on('import_jobs')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['import_job_id']);
            $table->dropIndex(['import_job_id']);
            $table->dropColumn('import_job_id');
        });
    }
};
