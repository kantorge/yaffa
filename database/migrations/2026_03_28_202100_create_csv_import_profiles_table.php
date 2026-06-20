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
        Schema::create('file_import_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnDelete();
            $table->string('key', 100)->nullable()->unique();
            $table->string('type', 20)->default('user');
            $table->string('file_type', 10)->default('csv');
            $table->string('name');
            $table->string('delimiter', 5)->nullable();
            $table->boolean('has_header_row')->default(true);
            $table->string('date_format', 64)->nullable();
            $table->string('decimal_separator', 10)->nullable();
            $table->string('thousand_separator', 10)->nullable();
            $table->string('sign_handling', 32)->nullable();
            $table->json('mapping_json')->nullable();
            $table->json('options_json')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'type']);
            $table->index('active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('file_import_profiles');
    }
};
