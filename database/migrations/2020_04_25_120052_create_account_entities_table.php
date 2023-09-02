<?php

use App\Models\User;
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
        Schema::create('account_entities', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->string('name', 191);
            $table->text('alias')->nullable();
            $table->boolean('active')->default('1');
            $table->morphs('config');
            $table->timestamps();

            // Make the name unique for each user and config_type
            $table->unique(['config_type', 'name', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     */
    public function down(): void
    {
        Schema::dropIfExists('account_entities');
    }
};
