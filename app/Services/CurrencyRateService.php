<?php

namespace App\Services;

use App\Models\CurrencyRate;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CurrencyRateService
{
    /**
     * Create a new currency rate.
     */
    public function createRate(array $data): CurrencyRate
    {
        return CurrencyRate::create([
            'from_id' => $data['from_id'],
            'to_id' => $data['to_id'],
            'date' => $data['date'],
            'rate' => $data['rate'],
        ]);
    }

    /**
     * Update an existing currency rate.
     */
    public function updateRate(CurrencyRate $currencyRate, array $data): CurrencyRate
    {
        $currencyRate->update([
            'date' => $data['date'],
            'rate' => $data['rate'],
        ]);

        return $currencyRate->fresh();
    }

    /**
     * Delete a currency rate.
     */
    public function deleteRate(CurrencyRate $currencyRate): bool
    {
        return $currencyRate->delete();
    }

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
