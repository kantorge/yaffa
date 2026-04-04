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
        Schema::create('investment_provider_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->string('provider_key');
            $table->text('credentials')->nullable();
            $table->json('options')->nullable();
            $table->boolean('enabled')->default(true);
            $table->text('last_error')->nullable();
            $table->json('rate_limit_overrides')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'provider_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('investment_provider_configs');
    }
};
