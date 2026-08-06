<?php

namespace Tests\Unit\Services;

use App\Services\InflationCalculator;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class InflationCalculatorTest extends TestCase
{
    public function test_amount_is_unchanged_within_the_start_year(): void
    {
        $calculator = new InflationCalculator();

        $result = $calculator->applyAnnualRate(
            100.0,
            10.0,
            Carbon::parse('2024-12-15'),
            Carbon::parse('2024-12-31'),
        );

        $this->assertSame(100.0, $result);
    }

    public function test_amount_compounds_at_the_next_calendar_year_boundary_not_the_start_date_anniversary(): void
    {
        $calculator = new InflationCalculator();

        $result = $calculator->applyAnnualRate(
            100.0,
            10.0,
            Carbon::parse('2024-12-15'),
            Carbon::parse('2025-01-01'),
        );

        $this->assertEqualsWithDelta(110.0, $result, 0.0001);
    }

    public function test_amount_compounds_again_at_the_following_calendar_year_boundary(): void
    {
        $calculator = new InflationCalculator();

        $result = $calculator->applyAnnualRate(
            100.0,
            10.0,
            Carbon::parse('2024-12-15'),
            Carbon::parse('2026-06-01'),
        );

        // Two calendar-year boundaries crossed: 2025-01-01 and 2026-01-01.
        $this->assertEqualsWithDelta(121.0, $result, 0.0001);
    }

    public function test_multi_year_compounding_from_a_january_start_date(): void
    {
        $calculator = new InflationCalculator();

        $result = $calculator->applyAnnualRate(
            1000.0,
            5.0,
            Carbon::parse('2020-01-01'),
            Carbon::parse('2024-01-01'),
        );

        $this->assertEqualsWithDelta(1000 * (1.05 ** 4), $result, 0.0001);
    }

    public function test_null_rate_is_a_no_op(): void
    {
        $calculator = new InflationCalculator();

        $result = $calculator->applyAnnualRate(
            250.0,
            null,
            Carbon::parse('2020-01-01'),
            Carbon::parse('2030-01-01'),
        );

        $this->assertSame(250.0, $result);
    }

    public function test_zero_rate_is_a_no_op(): void
    {
        $calculator = new InflationCalculator();

        $result = $calculator->applyAnnualRate(
            250.0,
            0.0,
            Carbon::parse('2020-01-01'),
            Carbon::parse('2030-01-01'),
        );

        $this->assertSame(250.0, $result);
    }

    public function test_target_date_before_start_date_is_a_no_op(): void
    {
        $calculator = new InflationCalculator();

        $result = $calculator->applyAnnualRate(
            250.0,
            10.0,
            Carbon::parse('2024-06-01'),
            Carbon::parse('2024-01-01'),
        );

        $this->assertSame(250.0, $result);
    }
}
