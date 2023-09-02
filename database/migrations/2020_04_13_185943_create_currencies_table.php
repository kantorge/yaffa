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
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->string('name', 191);
            $table->string('iso_code', 3);
            $table->tinyInteger('num_digits')->default('0');
            $table->boolean('base')->nullable();
            $table->boolean('auto_update')->default('1');
            $table->timestamps();

            // Add unique constraints per user
            $table->unique(['base', 'user_id']);
            $table->unique(['name', 'user_id']);
            $table->unique(['iso_code', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     */
    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
