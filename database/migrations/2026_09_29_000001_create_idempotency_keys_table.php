<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores the outcome of retry-safe API writes (see EnsureIdempotent middleware),
 * so a repeated request with the same Idempotency-Key replays the original response.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('route');
            $table->char('request_hash', 64);
            // Null while the original request is still being processed
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->longText('response_body')->nullable();
            $table->timestamp('created_at')->nullable()->index();

            $table->unique(['user_id', 'route', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
