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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->string('language', 2)->default('en');
            $table->string('locale', 10)->default('en-EN');
            $table->date('start_date')->default(DB::raw('(date_sub(CURRENT_TIMESTAMP, INTERVAL 30 DAY))'));
            $table->date('end_date')->default(DB::raw('(date_add(CURRENT_TIMESTAMP, INTERVAL 50 YEAR))'));
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
