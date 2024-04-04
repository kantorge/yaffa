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
        Schema::table('transaction_schedules', function (Blueprint $table) {
            $table->boolean('active')
                ->default(true)
                ->after('automatic_recording');
        });

        // Set the active flag to all existing schedules
        $schedules = App\Models\TransactionSchedule::all();
        $schedules->each(function ($schedule) {
            $schedule->active = $schedule->isActive();
            $schedule->saveQuietly();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transaction_schedules', function (Blueprint $table) {
            $table->dropColumn('active');
        });
    }
};
