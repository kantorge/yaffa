<?php

namespace App\Contracts;

use App\Models\Investment;
use Carbon\Carbon;

interface InvestmentPriceProvider
{
    /**
     * Fetch investment prices from external source
     *
     * @param  Investment  $investment  The investment to fetch prices for
     * @param  Carbon|null  $from  Start date for price history (null = latest only)
     * @param  bool  $refill  Whether to fetch full history vs. incremental
     * @return array Array of ['date' => 'Y-m-d', 'price' => float]
     *
     * @throws \App\Exceptions\PriceProviderException
     */
    public function fetchPrices(Investment $investment, ?Carbon $from = null, bool $refill = false): array;

    /**
     * Get provider name/identifier
     */
    public function getName(): string;

    /**
     * Whether this provider supports refilling historical data
     */
    public function supportsRefill(): bool;

    /**
     * Get human-readable display name (localized)
     */
    public function getDisplayName(): string;

    /**
     * Get provider description for UI (localized)
     */
    public function getDescription(): string;

    /**
     * Get usage instructions for configuration (localized)
     */
    public function getInstructions(): string;
}
