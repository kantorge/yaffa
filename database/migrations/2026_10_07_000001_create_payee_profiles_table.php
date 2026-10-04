<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('payee_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_entity_id')->unique()->constrained('account_entities')->cascadeOnDelete();
            $table->unsignedInteger('sample_size')->default(0);
            $table->foreignId('dominant_category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->decimal('dominant_share', 5, 4)->default(0);
            $table->unsignedInteger('single_item_dominant_count')->default(0);
            $table->decimal('wilson_lower', 5, 4)->default(0);
            $table->decimal('amount_median', 20, 4)->nullable();
            $table->decimal('amount_min', 20, 4)->nullable();
            $table->decimal('amount_max', 20, 4)->nullable();
            $table->decimal('amount_mode_share', 5, 4)->nullable();
            $table->json('known_amounts');
            $table->json('typical_account_ids');
            $table->decimal('multi_item_share', 5, 4)->default(0);
            $table->timestamp('calculated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payee_profiles');
    }
};
