<?php

namespace App\Http\Controllers;

use App\Http\Traits\CurrencyTrait;
use App\Http\Traits\ScheduleTrait;
use Illuminate\Http\Request;
use Illuminate\View\View;
use JavaScript;

class ReportController extends Controller
{
    use CurrencyTrait;
    use ScheduleTrait;

    public function __construct()
    {
        $this->middleware(['auth', 'verified']);
    }

    public function cashFlow(Request $request): View
    {
        /**
         * @get('/reports/cashflow')
         * @name('reports.cashflow')
         * @middlewares('web', 'auth', 'verified')
         */

        // Check if forecast is required
        $withForecast = $request->get('withForecast') ?? false;

        JavaScript::put([
            'presetAccount' => $request->get('account'),
        ]);

        return view(
            'reports.cashflow',
            [
                'withForecast' => $withForecast,
            ]
        );
    }

    public function budgetChart(Request $request)
    {
        /**
         * @get('/reports/budgetchart')
         * @name('reports.budgetchart')
         * @middlewares('web', 'auth', 'verified')
         */
        // Get requested aggregation period
        $byYears = $request->get('byYears') ?? false;

        // Pass currency related data for amCharts
        JavaScript::put([
            'presetCategories' => $request->get('categories', []),
            'byYears' => $byYears,
        ]);

        return view(
            'reports.budgetchart',
            [
                'byYears' => $byYears,
            ]
        );
    }

    /**
     * Display form for searching transactions. Pass any preset filters from query string.
     *
     * @param  Request  $request
     * @return View
     */
    public function transactionsByCriteria(Request $request): View
    {
        /**
         * @get('/reports/transactions')
         * @name('reports.transactions')
         * @middlewares('web', 'auth', 'verified')
         */
        // Get preset filters from query string
        $filters = [];
        if ($request->has('accounts')) {
            $filters['accounts'] = $request->get('accounts');
        }
        if ($request->has('payees')) {
            $filters['payees'] = $request->get('payees');
        }
        if ($request->has('categories')) {
            $filters['categories'] = $request->get('categories');
        }
        if ($request->has('tags')) {
            $filters['tags'] = $request->get('tags');
        }
        if ($request->has('date_from')) {
            $filters['date_from'] = $request->get('date_from');
        }
        if ($request->has('date_to')) {
            $filters['date_to'] = $request->get('date_to');
        }

        JavaScript::put([
            'filters' => $filters,
        ]);

        return view('reports.transactions');
    }

    public function getSchedules(): View
    {
        /**
         * @get('/reports/schedule')
         * @name('report.schedules')
         * @middlewares('web', 'auth', 'verified')
         */
        return view('reports.schedule');
    }
}
