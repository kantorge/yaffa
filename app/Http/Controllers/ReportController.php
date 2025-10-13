<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use App\Http\Traits\CurrencyTrait;
use App\Http\Traits\ScheduleTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Laracasts\Utilities\JavaScript\JavaScriptFacade;
use Laracasts\Utilities\JavaScript\JavaScriptFacade as JavaScript;

class ReportController extends Controller implements HasMiddleware
{
    use CurrencyTrait;
    use ScheduleTrait;

    public static function middleware(): array
    {
        return [
            ['auth', 'verified'],
        ];
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
            'presetAccount' => $request->get('accountEntity'),
        ]);

        return view(
            'reports.cashflow',
            [
                'withForecast' => $withForecast,
            ]
        );
    }

    public function budgetChart(Request $request): View
    {
        /**
         * @get('/reports/budgetchart')
         * @name('reports.budgetchart')
         * @middlewares('web', 'auth', 'verified')
         */

        return view('reports.budgetchart');
    }

    /**
     * Display form for searching transactions.
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

    /**
     * Display view with investment timeline chart.
     *
     * @param Request $request
     * @return View
     */
    public function investmentTimeline(Request $request): View
    {
        /**
         * @get('/reports/investment-timeline')
         * @name('reports.investment_timeline')
         * @middlewares('web', 'auth', 'verified')
         */

        // Pass data for JavaScript in the view
        JavaScriptFacade::put([
            'investmentGroups' => $request->user()->investmentGroups,
        ]);

        return view('reports.investment-timeline');
    }
}
