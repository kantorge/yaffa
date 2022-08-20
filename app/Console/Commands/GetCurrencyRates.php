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
    protected $signature = 'app:currency-rates:get';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run retrieval of currency rates for all base currencies and their currency pairs.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // Get all currencies of all users, which are not base currencies, and has autotmatic currency rate retrieval enabled
        $currencies = Currency::notBase()->where('auto_update', true)->get();

        // Loop all currencies and invoke the currency rate retrieval job
        $currencies->each(function ($currency) {
            GetCurrencyRatesJob::dispatch($currency);
        });

        return 0;
    }
}
