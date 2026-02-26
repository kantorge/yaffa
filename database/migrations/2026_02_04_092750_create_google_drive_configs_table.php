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
        Schema::create('google_drive_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->string('service_account_email');
            $table->text('service_account_json');
            $table->string('folder_id', 191);
            $table->boolean('delete_after_import')->default(false);
            $table->boolean('enabled')->default(true);
            $table->timestamp('last_sync_at')->nullable();
            $table->text('last_error')->nullable();
            $table->unsignedInteger('error_count')->default(0);
            $table->timestamps();

            $table->index('user_id');
            $table->index('enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('google_drive_configs');
    }
};
