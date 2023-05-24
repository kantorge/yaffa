<?php

namespace App\Jobs;

use App\Models\Currency;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GetCurrencyRates implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public Currency $currency;

    /**
     * Create a new job instance.
     */
    public function __construct(Currency $currency)
    {
        $this->currency = $currency;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Invoke missing currency rate retrieval method for the currency
        $this->currency->retreiveMissingCurrencyRateToBase();
    }
}
