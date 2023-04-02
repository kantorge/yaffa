<?php

namespace App\Console\Commands;

use App\Jobs\GetCurrencyRates as GetCurrencyRatesJob;
use App\Models\Currency;
use Illuminate\Console\Command;

class GetCurrencyRates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:currency-rates:get {iso_codes?*}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run retrieval of currency rates for all currencies against the base currency.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        // Check if specific currencies are requested
        $requestedCurrencies = $this->argument('iso_codes');

        // Get all currencies of all users, which are not base currencies,
        // and has autotmatic currency rate retrieval enabled
        $currencies = Currency::notBase()->autoUpdate()
            // Optionally apply currency filter
            ->when($requestedCurrencies, function ($query, $requestedCurrencies) {
                $query->whereIn('iso_code', $requestedCurrencies);
            })
            ->get();

        // Loop all currencies and invoke the currency rate retrieval job
        $currencies->each(function ($currency) {
            GetCurrencyRatesJob::dispatch($currency);
        });

        return Command::SUCCESS;
    }
}
