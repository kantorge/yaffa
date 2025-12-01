<?php

namespace App\Http\Traits;

use Carbon\Carbon;

/**
 * Trait for UK tax year calculations
 * UK tax year runs from 6 April to 5 April
 */
trait UkTaxYearTrait
{
    /**
     * Get the start date of the UK tax year for a given date
     */
    public function getTaxYearStart(Carbon $date): Carbon
    {
        // If date is before 6 April, tax year started previous year
        if ($date->month < 4 || ($date->month === 4 && $date->day < 6)) {
            return Carbon::create($date->year - 1, 4, 6, 0, 0, 0);
        }
        
        return Carbon::create($date->year, 4, 6, 0, 0, 0);
    }

    /**
     * Get the end date of the UK tax year for a given date
     */
    public function getTaxYearEnd(Carbon $date): Carbon
    {
        // If date is before 6 April, tax year ends this year
        if ($date->month < 4 || ($date->month === 4 && $date->day < 6)) {
            return Carbon::create($date->year, 4, 5, 23, 59, 59);
        }
        
        return Carbon::create($date->year + 1, 4, 5, 23, 59, 59);
    }

    /**
     * Get tax year label (e.g., "2024/25")
     */
    public function getTaxYearLabel(Carbon $date): string
    {
        $start = $this->getTaxYearStart($date);
        $end = $this->getTaxYearEnd($date);
        
        return $start->format('Y') . '/' . $end->format('y');
    }

    /**
     * Get list of available tax years from earliest transaction to current
     */
    public function getAvailableTaxYears(): array
    {
        $years = [];
        $currentYear = Carbon::now();
        
        // Start from 2020/21 tax year or earliest transaction year
        $startYear = Carbon::create(2020, 4, 6);
        
        $year = $startYear->copy();
        while ($year->lte($currentYear)) {
            $taxYearEnd = $this->getTaxYearEnd($year);
            $years[] = [
                'label' => $this->getTaxYearLabel($year),
                'start' => $this->getTaxYearStart($year)->toDateString(),
                'end' => $taxYearEnd->toDateString(),
                'year_value' => $this->getTaxYearStart($year)->format('Y'),
            ];
            $year->addYear();
        }
        
        return array_reverse($years);
    }

    /**
     * Parse tax year string (e.g., "2024/25") to get start/end dates
     */
    public function parseTaxYearString(string $taxYear): array
    {
        if (preg_match('/^(\d{4})\/\d{2}$/', $taxYear, $matches)) {
            $startYear = (int)$matches[1];
            $start = Carbon::create($startYear, 4, 6, 0, 0, 0);
            $end = Carbon::create($startYear + 1, 4, 5, 23, 59, 59);
            
            return [
                'start' => $start,
                'end' => $end,
                'label' => $taxYear,
            ];
        }
        
        // Default to current tax year
        $now = Carbon::now();
        return [
            'start' => $this->getTaxYearStart($now),
            'end' => $this->getTaxYearEnd($now),
            'label' => $this->getTaxYearLabel($now),
        ];
    }
}
