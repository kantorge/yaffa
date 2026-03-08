<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('failed_jobs')) {
            return;
        }

        if (! Schema::hasColumn('failed_jobs', 'uuid')) {
            Schema::table('failed_jobs', function (Blueprint $table) {
                $table->string('uuid')->nullable()->after('id');
            });
        }

        DB::table('failed_jobs')
            ->select('id')
            ->whereNull('uuid')
            ->orderBy('id')
            ->chunkById(100, function ($failedJobs) {
                foreach ($failedJobs as $failedJob) {
                    DB::table('failed_jobs')
                        ->where('id', $failedJob->id)
                        ->update(['uuid' => (string) Str::uuid()]);
                }
            });

        DB::statement('ALTER TABLE failed_jobs MODIFY uuid VARCHAR(191) NOT NULL');

        if (! Schema::hasIndex('failed_jobs', 'failed_jobs_uuid_unique')) {
            Schema::table('failed_jobs', function (Blueprint $table) {
                $table->unique('uuid');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('failed_jobs')) {
            return;
        }

        if (Schema::hasIndex('failed_jobs', 'failed_jobs_uuid_unique')) {
            Schema::table('failed_jobs', function (Blueprint $table) {
                $table->dropUnique('failed_jobs_uuid_unique');
            });
        }

        if (Schema::hasColumn('failed_jobs', 'uuid')) {
            Schema::table('failed_jobs', function (Blueprint $table) {
                $table->dropColumn('uuid');
            });
        }
    }
};
