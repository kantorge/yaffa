<?php

use App\Models\AccountEntity;
use App\Models\Category;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     */
    public function up(): void
    {
        Schema::create('account_entity_category_preference', function (Blueprint $table) {
            $table->foreignIdFor(AccountEntity::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Category::class)->constrained()->cascadeOnDelete();
            $table->boolean('preferred');
        });
    }

    /**
     * Reverse the migrations.
     *
     */
    public function down(): void
    {
        Schema::dropIfExists('account_entity_category_preference');
    }
};
