<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('job_performance', function (Blueprint $table) {
            $table->id();
            $table->string('job_class')->index();
            $table->string('job_id')->nullable()->index(); // UUID or unique identifier
            $table->json('job_parameters')->nullable(); // Store key job parameters
            $table->string('queue')->default('default');
            $table->timestamp('started_at')->index();
            $table->timestamp('finished_at')->nullable();
            $table->decimal('duration_seconds', 10, 3)->nullable()->index();
            $table->string('status', 20)->default('running')->index(); // running, completed, failed
            $table->text('error_message')->nullable();
            $table->unsignedBigInteger('memory_peak_mb')->nullable();
            $table->unsignedInteger('queries_count')->nullable();
            $table->timestamps();
            
            // Add index for common queries
            $table->index(['job_class', 'status']);
            $table->index(['job_class', 'started_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_performance');
    }
};
