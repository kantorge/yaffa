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
        Schema::create('category_learning', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->string('item_description', 255);
            $table->foreignId('category_id')
                ->constrained('categories')
                ->cascadeOnDelete();
            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'item_description']);
            $table->index('user_id');
            $table->index('category_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('category_learning');
    }
};
