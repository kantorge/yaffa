<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('google_drive_configs', function (Blueprint $table) {
            $table->string('folder_name')->nullable()->after('folder_id');
            $table->json('post_import_actions')->nullable()->after('folder_name');
            $table->string('processed_folder_id', 255)->nullable()->after('post_import_actions')->index();
            $table->string('processed_folder_name', 255)->nullable()->after('processed_folder_id');
        });

        // Data migration: convert delete_after_import=true rows to post_import_actions=["delete","trash"]
        DB::table('google_drive_configs')
            ->where('delete_after_import', true)
            ->update(['post_import_actions' => json_encode(['delete', 'trash'])]);

        Schema::table('google_drive_configs', function (Blueprint $table) {
            $table->dropColumn('delete_after_import');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('google_drive_configs', function (Blueprint $table) {
            $table->boolean('delete_after_import')->default(false)->after('folder_id');
        });

        // Restore delete_after_import=true for rows that had ["delete", "trash"] as the first two actions
        DB::table('google_drive_configs')
            ->whereNotNull('post_import_actions')
            ->get()
            ->each(function ($row) {
                $actions = json_decode($row->post_import_actions, true);
                if (is_array($actions) && in_array('delete', $actions, true)) {
                    DB::table('google_drive_configs')
                        ->where('id', $row->id)
                        ->update(['delete_after_import' => true]);
                }
            });

        Schema::table('google_drive_configs', function (Blueprint $table) {
            $table->dropIndex(['processed_folder_id']);
            $table->dropColumn(['folder_name', 'post_import_actions', 'processed_folder_id', 'processed_folder_name']);
        });
    }
};
