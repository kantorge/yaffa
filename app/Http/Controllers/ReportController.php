<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use App\Models\Transaction;
use App\Models\TransactionType;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use JavaScript;

class ReportController extends Controller
{
    public function cashFlow()
    {
        // Get monthly average currency rate for all currencies
        $baseCurrency = Currency::where('base', 1)->firstOr(function () {
            return Currency::orderBy('id')->firstOr(function () {
                return null;
            });
        });

        $allRates = $this->allCurrencyRatesByMonth()
            ->filter(function ($rate) use ($baseCurrency) {
                return $rate->to_id == $baseCurrency->id;
            });

        // Get all standard transactions (one-time AND scheduled)
        $standardTransactions = Transaction::where(
            function ($query) {
                $query->where('schedule', 1)
                    ->orWhere(function ($query) {
                        $query->where('schedule', 0);
                        $query->where('budget', 0);
                    });
            }
        )
        ->where(
            'config_type',
            'transaction_detail_standard'
        )
        // Exclude transfers
        ->where(
            'transaction_type_id',
            '!=',
            TransactionType::where('name', '=', 'transfer')->first()->id
        )
        // Load all necessary relations
        ->with([
            'config',
            'transactionType',
            'config.accountFrom.config',
            'config.accountTo.config',
        ])
        //->limit(10)
        ->get();

        // Get all investment transactions
        $investmentTransactions = Transaction::where(
            function ($query) {
                $query->where('schedule', 1)
                    ->orWhere(function ($query) {
                        $query->where('schedule', 0);
                        $query->where('budget', 0);
                    });
            }
        )
        ->where(
            'config_type',
            'transaction_detail_investment'
        )
        // Load all necessary relations
        ->with([
            'config',
            'transactionType',
            'config.account.config',
        ])
        //->limit(10)
        ->get();

        //dd($investmentTransactions);

        // Group standard transactions by month, and get all relevant details
        $standardCompact = [];
        $standardTransactions->filter(function ($transaction) {
            return !$transaction->schedule;
        })
        ->each(function ($transaction) use (&$standardCompact) {
            $month = $transaction->date->format('Y-m-01');
            $currency_id = ($transaction->transactionType->name === 'withdrawal' ? $transaction->config->accountFrom->config->currency_id : $transaction->config->accountTo->config->currency_id);

            $standardCompact[$month][$currency_id][] = $transaction->cashFlowValue(null);
        });

        // Group investment transactions
        $investmentCompact = [];
        $investmentTransactions->filter(function ($transaction) {
            return !$transaction->schedule;
        })
        ->each(function ($transaction) use (&$investmentCompact) {
            $month = $transaction->date->format('Y-m-01');
            $currency_id = $transaction->config->account->config->currency_id;

            $investmentCompact[$month][$currency_id][] = $transaction->cashFlowValue(null);
        });

        //dd($investmentCompact);

        // Summarize standard and investment items, applying currency rate
        $monthlyData = [];

        foreach ($standardCompact as $month => $monthData) {
            foreach ($monthData as $currency => $items) {
                if (!array_key_exists($month, $monthlyData)) {
                    $monthlyData[$month] = 0;
                }

                $rate = $allRates
                    ->where('month', $month)
                    ->where('from_id', $currency)
                    ->first();

                $monthlyData[$month] += array_sum($items) * ($rate ? $rate->rate : 1);
            }
        }

        foreach ($investmentCompact as $month => $monthData) {
            foreach ($monthData as $currency => $items) {
                if (!array_key_exists($month, $monthlyData)) {
                    $monthlyData[$month] = 0;
                }

                $rate = $allRates
                    ->where('month', $month)
                    ->where('from_id', $currency)
                    ->first();

                $monthlyData[$month] += array_sum($items) * ($rate ? $rate->rate : 1);
            }
        }

        //dd($monthlyData);

        // Convert monthly data into dataTables format
        $final = [];
        $runningTotal = 0;
        foreach ($monthlyData as $month => $data) {
            $runningTotal += $data;
            $final[] = [
                'month' => $month,
                'value' => $data,
                'runningTotal' => $runningTotal,
            ];
        }

        // Add schedule to history items, if needeed
        if (false) {
            $transactions
            ->filter(function ($transaction) {
                    return $transaction['transaction_group'] == 'schedule';
            })
            ->each(function ($transaction) use (&$transactions) {
                $rule = new Rule();
                $rule->setStartDate(new Carbon($transaction['schedule']->start_date));

                if ($transaction['schedule']->end_date) {
                    $rule->setUntil(new Carbon($transaction['schedule']->end_date));
                }

                $rule->setFreq($transaction['schedule']->frequency);

                if ($transaction['schedule']->count) {
                    $rule->setCount($transaction['schedule']->count);
                }
                if ($transaction['schedule']->interval) {
                    $rule->setInterval($transaction['schedule']->interval);
                }

                $transformer = new ArrayTransformer();

                $transformerConfig = new ArrayTransformerConfig();
                $transformerConfig->setVirtualLimit(100);
                $transformerConfig->enableLastDayOfMonthFix();
                $transformer->setConfig($transformerConfig);

                $startDate = new Carbon($transaction['schedule']->next_date);
                $startDate->startOfDay();
                if (is_null($transaction['schedule']->end_date)) {
                    $endDate = new Carbon('next year');
                } else {
                    $endDate = new Carbon($transaction['schedule']->end_date);
                }
                $endDate->startOfDay();

                $constraint = new BetweenConstraint($startDate, $endDate, true);

                $first = true;

                foreach ($transformer->transform($rule, $constraint) as $instance) {
                    $newTransaction = $transaction;
                    $newTransaction['date'] = $instance->getStart()->format('Y-m-d');
                    $newTransaction['transaction_group'] = 'forecast';
                    $newTransaction['schedule_is_first'] = $first;

                    $transactions->push($newTransaction);

                    $first = false;
                }
            });
        }

        JavaScript::put([
            'transactionDataHistory' => $final,
        ]);

        return view(
            'reports.cashflow'
        );
    }

    private function transformDate(Transaction $transaction)
    {
        if ($transaction->schedule) {
            $transaction->load(['transactionSchedule']);

            return [
                'schedule' => $transaction->transactionSchedule,
                'transaction_group' => 'schedule',
                'next_date' => ($transaction->transactionSchedule->next_date ? $transaction->transactionSchedule->next_date->format('Y-m-d') : null),
            ];
        }

        return [
            'date' => $transaction->date,
            'transaction_group' => 'history',
        ];
    }

     /**
     * Load a collection for all currencies, with an average rate by month
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function allCurrencyRatesByMonth()
    {
        return DB::table('currency_rates')
            ->select(
                DB::raw('SUBDATE(`date`, (day(`date`)-1)) AS `month`'),
                'from_id',
                'to_id',
                DB::raw('avg(rate) as rate')
            )
            ->groupBy(DB::raw('SUBDATE(`date`, (day(`date`)-1))'))
            ->groupBy('from_id')
            ->groupBy('to_id')
            ->get();
    }
}
