<?php

use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     */
    public function up(): void
    {
        Schema::table('categories', function ($table) {
            $table->dropForeign('categories_parent_id_foreign');

            $table->foreign('parent_id')
                ->references('id')
                ->on('categories')
                ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     */
    public function down(): void
    {
        Schema::table('categories', function ($table) {
            $table->dropForeign('categories_parent_id_foreign');

            $table->foreign('parent_id')
                ->references('id')
                ->on('categories')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }
};
