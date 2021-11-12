<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class AddUserIdToModels extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Ensure, that at least one row is available in users table. This is a support for users with existing data.
        if (DB::table('users')->count() < 1) {
            DB::table('users')->insert(
                [
                    'name' => 'Default user',
                    'email' => env('ADMIN_EMAIL', 'demo@yaffa.cc'),
                    'password' => Hash::make('password'),
                ]
            );
        }

        // Get first user
        $user = DB::table('users')->select('id')->orderBy('id')->first();

        Schema::table('account_groups', function (Blueprint $table) use ($user) {
            $table->foreignId('user_id')->nullable(false)->after('id')->default($user->id);
            $table->foreign('user_id')->references('id')->on('users');

            $table->dropUnique('account_groups_name_unique');
            $table->unique(['name', 'user_id'], 'account_groups_name_user_unique');
        });

        Schema::table('account_entities', function (Blueprint $table) use ($user) {
            $table->foreignId('user_id')->nullable(false)->after('id')->default($user->id);
            $table->foreign('user_id')->references('id')->on('users');

            $table->dropUnique('account_entities_config_type_name_unique');
            $table->unique(['config_type', 'name', 'user_id'], 'account_entities_name_type_user_unique');
        });

        Schema::table('categories', function (Blueprint $table) use ($user) {
            $table->foreignId('user_id')->nullable(false)->after('id')->default($user->id);
            $table->foreign('user_id')->references('id')->on('users');
        });

        Schema::table('currencies', function (Blueprint $table) use ($user) {
            $table->foreignId('user_id')->nullable(false)->after('id')->default($user->id);
            $table->foreign('user_id')->references('id')->on('users');

            $table->dropUnique('currencies_base_unique');
            $table->dropUnique('currencies_iso_code_unique');
            $table->dropUnique('currencies_name_unique');

            $table->unique(['base', 'user_id'], 'currencies_base_user_unique');
            $table->unique(['name', 'user_id'], 'currencies_name_user_unique');
            $table->unique(['iso_code', 'user_id'], 'currencies_iso_code_user_unique');
        });

        Schema::table('investment_groups', function (Blueprint $table) use ($user) {
            $table->foreignId('user_id')->nullable(false)->after('id')->default($user->id);
            $table->foreign('user_id')->references('id')->on('users');

            $table->dropUnique('investment_groups_name_unique');
            $table->unique(['name', 'user_id'], 'investment_groups_name_user_unique');
        });

        Schema::table('investments', function (Blueprint $table) use ($user) {
            $table->foreignId('user_id')->nullable(false)->after('id')->default($user->id);
            $table->foreign('user_id')->references('id')->on('users');

            $table->dropUnique('investments_name_unique');
            $table->dropUnique('investments_symbol_unique');

            $table->unique(['name', 'user_id'], 'investments_name_user_unique');
            $table->unique(['symbol', 'user_id'], 'investments_symbol_user_unique');
        });

        Schema::table('tags', function (Blueprint $table) use ($user) {
            $table->foreignId('user_id')->nullable(false)->after('id')->default($user->id);
            $table->foreign('user_id')->references('id')->on('users');

            $table->dropUnique('tags_name_unique');
            $table->unique(['name', 'user_id'], 'tags_name_user_unique');
        });

        Schema::table('transactions', function (Blueprint $table) use ($user) {
            $table->foreignId('user_id')->nullable(false)->after('id')->default($user->id);
            $table->foreign('user_id')->references('id')->on('users');
        });

        // Remove user_id temporary default values
        DB::statement('ALTER TABLE account_groups ALTER COLUMN user_id DROP DEFAULT');
        DB::statement('ALTER TABLE account_entities ALTER COLUMN user_id DROP DEFAULT');
        DB::statement('ALTER TABLE categories ALTER COLUMN user_id DROP DEFAULT');
        DB::statement('ALTER TABLE currencies ALTER COLUMN user_id DROP DEFAULT');
        DB::statement('ALTER TABLE investment_groups ALTER COLUMN user_id DROP DEFAULT');
        DB::statement('ALTER TABLE investments ALTER COLUMN user_id DROP DEFAULT');
        DB::statement('ALTER TABLE tags ALTER COLUMN user_id DROP DEFAULT');
        DB::statement('ALTER TABLE transactions ALTER COLUMN user_id DROP DEFAULT');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('account_groups', function (Blueprint $table) {
            $table->dropForeign('account_groups_user_id_foreign');
            $table->dropColumn('user_id');

            $table->dropUnique('account_groups_name_user_unique');
            $table->unique('name');
        });

        Schema::table('account_entities', function (Blueprint $table) {
            $table->dropForeign('account_entities_user_id_foreign');
            $table->dropColumn('user_id');

            $table->dropUnique('account_entities_name_type_user_unique');
            $table->unique(['config_type', 'name']);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropForeign('categories_user_id_foreign');
            $table->dropColumn('user_id');
        });

        Schema::table('currencies', function (Blueprint $table) {
            $table->dropForeign('currencies_user_id_foreign');
            $table->dropColumn('user_id');

            $table->dropUnique('currencies_base_user_unique');
            $table->dropUnique('currencies_name_user_unique');
            $table->dropUnique('currencies_iso_code_user_unique');

            $table->unique('name');
            $table->unique('iso_code');
            $table->unique('base');
        });

        Schema::table('investment_groups', function (Blueprint $table) {
            $table->dropForeign('investment_groups_user_id_foreign');
            $table->dropColumn('user_id');

            $table->dropUnique('investment_groups_name_user_unique');
            $table->unique('name');
        });

        Schema::table('investments', function (Blueprint $table) {
            $table->dropForeign('investments_user_id_foreign');
            $table->dropColumn('user_id');

            $table->dropUnique('investments_name_user_unique');
            $table->dropUnique('investments_symbol_user_unique');

            $table->unique('name');
            $table->unique('symbol');
        });

        Schema::table('tags', function (Blueprint $table) {
            $table->dropForeign('tags_user_id_foreign');
            $table->dropColumn('user_id');

            $table->dropUnique('tags_name_user_unique');
            $table->unique('name');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign('transactions_user_id_foreign');
            $table->dropColumn('user_id');
        });
    }
}
