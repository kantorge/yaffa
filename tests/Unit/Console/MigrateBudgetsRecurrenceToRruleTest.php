<?php

namespace Tests\Unit\Console;

use App\Console\Commands\MigrateBudgetsRecurrenceToRrule;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Golden-output coverage for the temporary, personal-instance-only budgets recurrence backfill
 * command's row-rebuild logic - delete alongside
 * app/Console/Commands/MigrateBudgetsRecurrenceToRrule.php once the affected instance has been
 * migrated (see that file's docblock).
 */
class MigrateBudgetsRecurrenceToRruleTest extends TestCase
{
    private function buildRrule(array $row): string
    {
        $method = new ReflectionMethod(MigrateBudgetsRecurrenceToRrule::class, 'buildRruleString');
        $method->setAccessible(true);

        return $method->invoke(new MigrateBudgetsRecurrenceToRrule(), (object) array_merge([
            'start_date' => '2024-01-01',
            'interval' => 1,
            'end_date' => null,
            'count' => null,
            'by_day' => null,
            'by_month' => null,
        ], $row));
    }

    public function test_plain_monthly_rule(): void
    {
        $this->assertSame(
            'FREQ=MONTHLY;INTERVAL=1',
            $this->buildRrule(['frequency' => 'MONTHLY']),
        );
    }

    public function test_rule_with_count(): void
    {
        $this->assertSame(
            'FREQ=MONTHLY;COUNT=3;INTERVAL=1',
            $this->buildRrule(['frequency' => 'MONTHLY', 'count' => 3]),
        );
    }

    public function test_rule_with_end_date(): void
    {
        $this->assertSame(
            'FREQ=YEARLY;UNTIL=20251231T000000;INTERVAL=1',
            $this->buildRrule(['frequency' => 'YEARLY', 'end_date' => '2025-12-31']),
        );
    }

    public function test_ordinal_weekday_rule_pinned_to_a_month(): void
    {
        $this->assertSame(
            'FREQ=YEARLY;INTERVAL=1;BYDAY=-1FR;BYMONTH=11',
            $this->buildRrule(['frequency' => 'YEARLY', 'by_day' => '-1FR', 'by_month' => 11]),
        );
    }
}
