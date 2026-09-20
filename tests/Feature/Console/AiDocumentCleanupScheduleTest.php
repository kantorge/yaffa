<?php

namespace Tests\Feature\Console;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Facade;
use Tests\TestCase;

class AiDocumentCleanupScheduleTest extends TestCase
{
    /**
     * Re-registers routes/console.php on a fresh schedule, as the schedule is built once at boot.
     *
     * @return list<Event>
     */
    private function cleanupEvents(bool $runsScheduler): array
    {
        config(['yaffa.runs_scheduler' => $runsScheduler]);

        $schedule = new Schedule();
        $this->app->instance(Schedule::class, $schedule);
        Facade::clearResolvedInstance(Schedule::class);

        require base_path('routes/console.php');

        return array_values(array_filter(
            $schedule->events(),
            fn (Event $event) => str_contains($event->command, 'ai-documents:cleanup-old-files')
        ));
    }

    public function test_cleanup_runs_daily_at_half_past_three_on_the_scheduler_container(): void
    {
        $events = $this->cleanupEvents(true);

        $this->assertCount(1, $events);
        $this->assertSame('30 3 * * *', $events[0]->expression);
    }

    public function test_cleanup_is_not_scheduled_on_other_containers(): void
    {
        $this->assertSame([], $this->cleanupEvents(false));
    }
}
