<?php

namespace App\Http\Controllers;

use App\Http\Traits\CurrencyTrait;
use App\Http\Traits\ScheduleTrait;
use App\Http\Traits\UkTaxYearTrait;
use App\Services\UnrealisedInterestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Carbon\Carbon;
use Laracasts\Utilities\JavaScript\JavaScriptFacade;
use Laracasts\Utilities\JavaScript\JavaScriptFacade as JavaScript;

class ReportController extends Controller
{
    use CurrencyTrait;
    use ScheduleTrait;
    use UkTaxYearTrait;

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

    public function unrealisedInterest(Request $request): View
    {
        /**
         * @get('/reports/unrealised-interest')
         * @name('reports.unrealised_interest')
         * @middlewares('web', 'auth', 'verified')
         */
        $service = new UnrealisedInterestService();
        
        // Get tax year from request or use current
        $taxYear = $request->get('tax_year');
        
        if ($taxYear) {
            // Parse tax year like "2024/25"
            $dates = $this->parseTaxYearString($taxYear);
            $startDate = $dates['start'];
            $endDate = $dates['end'];
            $label = $this->getTaxYearLabel($startDate);
        } else {
            // Default to current tax year
            $today = Carbon::today();
            $startDate = $this->getTaxYearStart($today);
            $endDate = $this->getTaxYearEnd($today);
            $label = $this->getTaxYearLabel($startDate);
        }

        $report = $service->getUnrealisedInterestReport(Auth::id(), $startDate, $endDate);
        $availableYears = $this->getAvailableTaxYears();

        return view('reports.unrealised-interest', [
            'report' => $report,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'label' => $label,
            'taxYear' => $taxYear,
            'availableYears' => $availableYears,
        ]);
    }
}
