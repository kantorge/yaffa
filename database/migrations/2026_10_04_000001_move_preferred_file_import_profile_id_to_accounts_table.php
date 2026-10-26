<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * The preferred import profile is an account-only setting, so it belongs to accounts, not to account_entities (shared with payees).
     */
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->foreignId('preferred_file_import_profile_id')
                ->nullable()
                ->constrained('file_import_profiles')
                ->restrictOnDelete();
        });

        DB::table('accounts')
            ->join('account_entities', function ($join) {
                $join->on('account_entities.config_id', '=', 'accounts.id')
                    ->where('account_entities.config_type', 'account');
            })
            ->whereNotNull('account_entities.preferred_file_import_profile_id')
            ->update(['accounts.preferred_file_import_profile_id' => DB::raw('account_entities.preferred_file_import_profile_id')]);

        Schema::table('account_entities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('preferred_file_import_profile_id');
        });
    }

    public function down(): void
    {
        Schema::table('account_entities', function (Blueprint $table) {
            $table->foreignId('preferred_file_import_profile_id')
                ->nullable()
                ->after('config_id')
                ->constrained('file_import_profiles')
                ->restrictOnDelete();
        });

        DB::table('account_entities')
            ->join('accounts', function ($join) {
                $join->on('account_entities.config_id', '=', 'accounts.id')
                    ->where('account_entities.config_type', 'account');
            })
            ->whereNotNull('accounts.preferred_file_import_profile_id')
            ->update(['account_entities.preferred_file_import_profile_id' => DB::raw('accounts.preferred_file_import_profile_id')]);

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('preferred_file_import_profile_id');
        });
    }
};
