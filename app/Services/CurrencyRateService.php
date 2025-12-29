<?php

namespace App\Services;

use App\Models\CurrencyRate;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CurrencyRateService
{
    /**
     * Get currency rates filtered by date range.
     */
    public function getRatesByDateRange(int $fromId, int $toId, ?string $dateFrom = null, ?string $dateTo = null): Collection
    {
        $query = CurrencyRate::where('from_id', $fromId)
            ->where('to_id', $toId)
            ->orderBy('date');

        if ($dateFrom) {
            $query->where('date', '>=', Carbon::parse($dateFrom));
        }

        if ($dateTo) {
            $query->where('date', '<=', Carbon::parse($dateTo));
        }

        return $query->get();
    }

    /**
     * Get all currency rates for a currency pair.
     */
    public function getAllRates(int $fromId, int $toId): Collection
    {
        return CurrencyRate::where('from_id', $fromId)
            ->where('to_id', $toId)
            ->orderBy('date')
            ->get();
    }
}
