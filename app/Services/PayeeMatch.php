<?php

namespace App\Services;

use App\Models\AccountEntity;

final readonly class PayeeMatch
{
    public const string TIER_EXACT = 'exact';

    public const string TIER_LEADING_TOKEN = 'leading_token';

    public const string TIER_SIMILARITY = 'similarity';

    /**
     * @param  float  $margin  Lead over the next best payee; 1.0 for the deterministic tiers
     * @param  bool  $autoEligible  Safe to act on without a person looking at it
     */
    public function __construct(
        public AccountEntity $payee,
        public string $tier,
        public float $score,
        public float $margin,
        public bool $autoEligible,
    ) {
    }
}
